<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function sales(): User
    {
        return User::where('email', 'sales@erp.local')->firstOrFail();
    }

    public function test_salesman_records_an_enquiry_with_running_doc_no(): void
    {
        $customer = Customer::first();
        $res = $this->actingAs($this->sales())->post('/enquiries', [
            'customer_id' => $customer->id, 'enquiry_date' => today()->toDateString(), 'service' => 'Roof Coating',
            'stage' => 'new', 'site_address' => '1 Jalan Test',
        ]);
        $e = Enquiry::latest('id')->first();
        $res->assertRedirect(route('enquiries.show', $e));
        $this->assertSame('Roof Coating', $e->service);
        $this->assertMatchesRegularExpression('#^ENQ-\d{4}/\d{4}$#', $e->doc_no);
        $this->assertSame($customer->branch_id, $e->branch_id);
    }

    public function test_lost_stage_requires_reason_on_form_but_board_patch_returns_json(): void
    {
        $e = Enquiry::where('stage', 'new')->firstOrFail();
        $this->actingAs($this->sales())->put("/enquiries/{$e->id}", $e->only(['customer_id', 'service']) + ['enquiry_date' => $e->enquiry_date->toDateString(), 'stage' => 'lost'])
            ->assertSessionHasErrors('lost_reason');

        $this->actingAs($this->sales())->patchJson("/enquiries/{$e->id}/stage", ['stage' => 'inspection'])
            ->assertOk()->assertJson(['ok' => true, 'stage' => 'inspection']);
        $this->assertSame('inspection', $e->fresh()->stage);

        $this->actingAs($this->sales())->patchJson("/enquiries/{$e->id}/stage", ['stage' => 'bogus'])->assertStatus(422);
    }

    public function test_quotation_totals_are_recomputed_on_server(): void
    {
        $e = Enquiry::where('stage', 'inspection')->firstOrFail();
        $item = Item::first();
        $this->actingAs($this->sales())->post('/quotations', [
            'customer_id' => $e->customer_id, 'enquiry_id' => $e->id, 'quote_date' => today()->toDateString(),
            'discount' => 10, 'tax_rate' => 8, 'action' => 'send',
            // client-supplied totals/amounts must be ignored
            'subtotal' => 1, 'total' => 1,
            'lines' => [
                ['item_id' => $item->id, 'description' => '', 'qty' => 3, 'uom' => 'pcs', 'unit_price' => 100, 'amount' => 999],
                ['description' => 'Labour', 'qty' => 2.5, 'uom' => 'DAY', 'unit_price' => 40],
                ['description' => '', 'qty' => 1, 'unit_price' => 0], // blank row is skipped
            ],
        ])->assertSessionHasNoErrors();

        $q = Quotation::latest('id')->with('lines')->first();
        $this->assertCount(2, $q->lines);
        $this->assertSame($item->name, $q->lines[0]->description);
        $this->assertSame('PCS', $q->lines[0]->uom);
        $this->assertEquals(400.00, (float) $q->subtotal);   // 300 + 100
        $this->assertEquals(10.00, (float) $q->discount);
        $this->assertEquals(31.20, (float) $q->tax);         // 8% of 390
        $this->assertEquals(421.20, (float) $q->total);
        $this->assertSame('sent', $q->status);
        $this->assertSame('quotation', $e->fresh()->stage, 'sending a quote advances the enquiry');
    }

    public function test_quotation_needs_a_described_line(): void
    {
        $this->actingAs($this->sales())->post('/quotations', [
            'customer_id' => Customer::first()->id, 'quote_date' => today()->toDateString(),
            'lines' => [['description' => '', 'qty' => 1, 'unit_price' => 5]],
        ])->assertSessionHasErrors('lines');
    }

    public function test_accept_then_convert_to_deposit_invoice_then_pay_partial_then_full(): void
    {
        $q = Quotation::where('status', 'sent')->whereNotNull('enquiry_id')->firstOrFail();
        $user = $this->sales();

        $this->actingAs($user)->post("/quotations/{$q->id}/status", ['status' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $q->fresh()->status);
        $this->assertSame('won', $q->enquiry->fresh()->stage);

        $this->actingAs($user)->post("/quotations/{$q->id}/invoice", ['type' => 'invoice', 'percent' => 50]);
        $inv = Invoice::where('quotation_id', $q->id)->latest('id')->firstOrFail();
        $this->assertSame('draft', $inv->status);
        $this->assertEquals(round(((float) $q->subtotal - (float) $q->discount) / 2 * (1 + (float) $q->tax_rate / 100), 2), (float) $inv->total);

        // drafts can't take payments
        $this->actingAs($user)->post("/invoices/{$inv->id}/payments", ['paid_on' => today()->toDateString(), 'amount' => 10, 'method' => 'Cash'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)->post("/invoices/{$inv->id}/issue")->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $inv->fresh()->status);

        $this->actingAs($user)->post("/invoices/{$inv->id}/payments", ['paid_on' => today()->toDateString(), 'amount' => 100, 'method' => 'Cash'])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertSame('partial', $inv->status);
        $this->assertEquals(100.0, (float) $inv->paid_amount);

        // overpaying is refused
        $this->actingAs($user)->post("/invoices/{$inv->id}/payments", ['paid_on' => today()->toDateString(), 'amount' => $inv->balance + 1, 'method' => 'Cash'])
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)->post("/invoices/{$inv->id}/payments", ['paid_on' => today()->toDateString(), 'amount' => $inv->balance, 'method' => 'DuitNow'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $inv->fresh()->status);

        // removing a payment re-opens the balance
        $this->actingAs($user)->delete('/payments/'.$inv->payments()->latest('id')->first()->id);
        $this->assertSame('partial', $inv->fresh()->status);
    }

    public function test_invoice_with_payment_cannot_be_voided_or_edited(): void
    {
        $inv = Invoice::where('status', 'paid')->firstOrFail();
        $this->actingAs($this->sales())->post("/invoices/{$inv->id}/void")->assertSessionHas('error');
        $this->assertSame('paid', $inv->fresh()->status);
        $this->actingAs($this->sales())->get("/invoices/{$inv->id}/edit")->assertRedirect(route('invoices.show', $inv));
    }

    public function test_reports_and_permissions(): void
    {
        $this->actingAs($this->sales())->get('/reports/customers?from=2026-01-01')->assertOk()->assertSee('Customer Report');
        $this->actingAs($this->sales())->get('/reports/salesman?from=2026-01-01')->assertOk()->assertSee('Ong Kah Hoe');
        $this->actingAs($this->sales())->get('/enquiries?view=board')->assertOk()->assertSee('data-kanban', false);

        $staff = User::where('email', 'staff@erp.local')->firstOrFail();
        $this->actingAs($staff)->get('/quotations')->assertForbidden();
        $this->actingAs($staff)->get('/enquiries')->assertForbidden();
    }
}
