<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $hq;

    private Warehouse $jb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@erp.local')->firstOrFail();
        $this->hq = Warehouse::where('code', 'KV-HQ')->firstOrFail();
        $this->jb = Warehouse::where('code', 'JB-01')->firstOrFail();
    }

    private function balance(Item $item, Warehouse $wh): float
    {
        return (float) StockBalance::where('item_id', $item->id)->where('warehouse_id', $wh->id)->value('qty');
    }

    public function test_inventory_pages_render_for_permitted_roles(): void
    {
        $po = PurchaseOrder::first();
        $pages = ['/items', '/items?stock=low', '/items/1', '/items/create', '/stock', '/stock?type=in', '/suppliers', '/suppliers/1',
            '/purchase-orders', "/purchase-orders/{$po->id}", '/purchase-orders/create', '/warehouses', '/worker-rates'];
        foreach ($pages as $p) {
            $this->actingAs($this->admin)->get($p)->assertOk();
        }
        $store = User::where('email', 'store@erp.local')->first();
        $this->actingAs($store)->get('/items')->assertOk()->assertSee('New item');
        $supervisor = User::where('email', 'supervisor@erp.local')->first(); // inventory.view only
        $this->actingAs($supervisor)->get('/items')->assertOk()->assertDontSee('New item');
        $this->actingAs($supervisor)->get('/purchase-orders')->assertForbidden();
        $this->actingAs($supervisor)->post('/stock', [])->assertForbidden();
        $staff = User::where('email', 'staff@erp.local')->first();
        $this->actingAs($staff)->get('/items')->assertForbidden();
    }

    public function test_stock_in_updates_moving_average_cost(): void
    {
        $item = Item::where('sku', 'ZC-GR26')->first();
        $onHand = (float) StockBalance::where('item_id', $item->id)->sum('qty');
        $oldCost = (float) $item->unit_cost;
        $hqBefore = $this->balance($item, $this->hq);

        $this->actingAs($this->admin)->from('/items/'.$item->id)
            ->post('/stock', ['type' => 'in', 'item_id' => $item->id, 'warehouse_id' => $this->hq->id, 'qty' => 10, 'unit_cost' => 50])
            ->assertRedirect('/items/'.$item->id)->assertSessionHasNoErrors();

        $expected = round(($onHand * $oldCost + 10 * 50) / ($onHand + 10), 4);
        $this->assertEqualsWithDelta($expected, (float) $item->fresh()->unit_cost, 0.0001);
        $this->assertEqualsWithDelta($hqBefore + 10, $this->balance($item, $this->hq), 0.001);
    }

    public function test_stock_out_beyond_balance_is_rejected_and_reopens_dialog(): void
    {
        $item = Item::where('sku', 'FE-6KG')->first();
        $before = $this->balance($item, $this->hq);

        $this->actingAs($this->admin)->from('/stock')
            ->post('/stock', ['type' => 'out', 'item_id' => $item->id, 'warehouse_id' => $this->hq->id, 'qty' => $before + 5])
            ->assertRedirect('/stock')->assertSessionHasErrors('qty')->assertSessionHas('reopen', 'dlg-stock');

        $this->assertEqualsWithDelta($before, $this->balance($item, $this->hq), 0.001);

        // A negative number on a non-adjust type is refused rather than silently flipped.
        $this->actingAs($this->admin)->from('/stock')
            ->post('/stock', ['type' => 'in', 'item_id' => $item->id, 'warehouse_id' => $this->hq->id, 'qty' => -1])
            ->assertSessionHasErrors('qty');
    }

    public function test_issue_to_project_reduces_stock_and_books_material_cost(): void
    {
        $item = Item::where('sku', 'SEAL-PU')->first();
        $project = Project::first();
        $before = $this->balance($item, $this->hq);
        $costsBefore = ProjectCost::where('project_id', $project->id)->count();

        $this->actingAs($this->admin)->from('/stock')
            ->post('/stock', ['type' => 'project', 'item_id' => $item->id, 'warehouse_id' => $this->hq->id, 'qty' => 3, 'project_id' => $project->id])
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta($before - 3, $this->balance($item, $this->hq), 0.001);
        $this->assertSame($costsBefore + 1, ProjectCost::where('project_id', $project->id)->count());
        $cost = ProjectCost::where('project_id', $project->id)->latest('id')->first();
        $this->assertSame('Material', $cost->category);
        $this->assertEqualsWithDelta(round(3 * (float) $item->unit_cost, 2), (float) $cost->amount, 0.01);

        // Project is mandatory for a project issue.
        $this->actingAs($this->admin)->from('/stock')
            ->post('/stock', ['type' => 'project', 'item_id' => $item->id, 'warehouse_id' => $this->hq->id, 'qty' => 1])
            ->assertSessionHasErrors('project_id');
    }

    public function test_transfer_moves_stock_between_warehouses(): void
    {
        $item = Item::where('sku', 'SCR-ROOF')->first();
        $hqBefore = $this->balance($item, $this->hq);
        $jbBefore = $this->balance($item, $this->jb);

        $this->actingAs($this->admin)->from('/stock')
            ->post('/stock/transfer', ['item_id' => $item->id, 'from_warehouse_id' => $this->hq->id, 'to_warehouse_id' => $this->jb->id, 'qty' => 2])
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta($hqBefore - 2, $this->balance($item, $this->hq), 0.001);
        $this->assertEqualsWithDelta($jbBefore + 2, $this->balance($item, $this->jb), 0.001);

        $this->actingAs($this->admin)->from('/stock')
            ->post('/stock/transfer', ['item_id' => $item->id, 'from_warehouse_id' => $this->hq->id, 'to_warehouse_id' => $this->hq->id, 'qty' => 1])
            ->assertSessionHasErrors('to_warehouse_id')->assertSessionHas('reopen', 'dlg-transfer');
    }

    public function test_purchase_order_create_partial_and_full_receipt(): void
    {
        $supplier = Supplier::first();
        $a = Item::where('sku', 'GUT-6')->first();
        $b = Item::where('sku', 'PRIMER-B')->first();
        $aBefore = $this->balance($a, $this->hq);

        $this->actingAs($this->admin)->post('/purchase-orders', [
            'supplier_id' => $supplier->id, 'warehouse_id' => $this->hq->id, 'order_date' => now()->toDateString(), 'submit' => 'order',
            'lines' => [
                ['item_id' => $a->id, 'qty' => 10, 'unit_price' => 90],
                ['item_id' => '', 'qty' => 1, 'unit_price' => ''], // blank editor row is ignored
                ['item_id' => $b->id, 'qty' => 4, 'unit_price' => 99.5],
            ],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::latest('id')->first();
        $this->assertSame('ordered', $po->status);
        $this->assertCount(2, $po->lines);
        $this->assertEqualsWithDelta(10 * 90 + 4 * 99.5, (float) $po->total, 0.01);
        $lineA = $po->lines->firstWhere('item_id', $a->id);
        $lineB = $po->lines->firstWhere('item_id', $b->id);

        // Over-receiving is refused.
        $this->actingAs($this->admin)->from("/purchase-orders/{$po->id}")
            ->post("/purchase-orders/{$po->id}/receive", ['receive' => [$lineA->id => 11]])
            ->assertSessionHasErrors("receive.{$lineA->id}");

        // Partial receipt.
        $this->actingAs($this->admin)->from("/purchase-orders/{$po->id}")
            ->post("/purchase-orders/{$po->id}/receive", ['receive' => [$lineA->id => 6, $lineB->id => 0], 'reference' => 'DO-1'])
            ->assertSessionHasNoErrors();
        $po->refresh();
        $this->assertSame('partial', $po->status);
        $this->assertEqualsWithDelta($aBefore + 6, $this->balance($a, $this->hq), 0.001);
        $this->assertEqualsWithDelta(90, (float) SupplierPrice::where('supplier_id', $supplier->id)->where('item_id', $a->id)->latest('id')->value('unit_price'), 0.001);

        // Receive the rest.
        $this->actingAs($this->admin)->from("/purchase-orders/{$po->id}")
            ->post("/purchase-orders/{$po->id}/receive", ['receive' => [$lineA->id => 4, $lineB->id => 4]])
            ->assertSessionHasNoErrors();
        $this->assertSame('received', $po->fresh()->status);
        $this->assertEqualsWithDelta($aBefore + 10, $this->balance($a, $this->hq), 0.001);

        // A received PO can neither be cancelled nor edited.
        $this->actingAs($this->admin)->from("/purchase-orders/{$po->id}")->post("/purchase-orders/{$po->id}/status", ['status' => 'cancelled'])->assertSessionHas('error');
        $this->actingAs($this->admin)->get("/purchase-orders/{$po->id}/edit")->assertRedirect();
    }

    public function test_draft_po_must_be_ordered_before_receiving(): void
    {
        $po = PurchaseOrder::where('status', 'draft')->firstOrFail();
        $line = $po->lines()->first();
        $this->actingAs($this->admin)->from("/purchase-orders/{$po->id}")
            ->post("/purchase-orders/{$po->id}/receive", ['receive' => [$line->id => 1]])
            ->assertSessionHas('error');
        $this->assertSame(0.0, (float) $line->fresh()->received_qty);
    }

    public function test_item_with_history_cannot_be_deleted(): void
    {
        $item = Item::where('sku', 'ZC-GR26')->first();
        $this->actingAs($this->admin)->from('/items/'.$item->id)->delete('/items/'.$item->id)->assertSessionHas('error');
        $this->assertNotSoftDeleted($item);
    }
}
