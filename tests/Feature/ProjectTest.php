<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Warranty;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@erp.local')->first();
    }

    public function test_project_pages_render_for_admin(): void
    {
        $p = Project::first();
        $this->actingAs($this->admin());
        foreach (['/projects', '/projects?view=board', '/projects/calendar', '/projects/schedule', "/projects/{$p->id}", '/projects/create',
            '/costing/projects', '/costing/summary?group=category', '/costing/summary?group=branch', '/warranties', '/service-requests',
            '/service-requests/calendar', '/incidents'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_create_project_from_enquiry_numbers_it_and_marks_enquiry_won(): void
    {
        $enquiry = Enquiry::where('stage', 'inspection')->first();
        $this->actingAs($this->admin())->get('/projects/create?enquiry_id='.$enquiry->id)->assertOk()->assertSee($enquiry->service);

        $res = $this->post('/projects', [
            'name' => 'Test roof job', 'customer_id' => $enquiry->customer_id, 'enquiry_id' => $enquiry->id, 'category' => 'normal',
            'status' => 'planning', 'start_date' => '2026-11-02', 'end_date' => '2026-11-06', 'contract_value' => '12000', 'budget_cost' => '',
        ]);
        $project = Project::where('name', 'Test roof job')->firstOrFail();
        $res->assertRedirect(route('projects.show', $project));
        $this->assertMatchesRegularExpression('#^PR-\d{4}/\d{4}$#', $project->code);
        $this->assertSame('0.00', (string) $project->budget_cost);
        $this->assertSame('won', $enquiry->fresh()->stage);
        $this->assertSame($project->id, $enquiry->fresh()->project_id);
    }

    public function test_status_patch_returns_json_for_kanban_and_completion_sets_progress(): void
    {
        $p = Project::where('status', 'in_progress')->first();
        $this->actingAs($this->admin())
            ->patchJson(route('projects.status', $p), ['status' => 'completed'])
            ->assertOk()->assertJson(['ok' => true, 'status' => 'completed', 'progress' => 100]);

        $this->patchJson(route('projects.status', $p), ['status' => 'bogus'])->assertStatus(422);
    }

    public function test_staff_cannot_view_and_salesman_cannot_change_status(): void
    {
        $p = Project::first();
        $this->actingAs(User::where('email', 'staff@erp.local')->first())->get('/projects')->assertForbidden();
        $this->actingAs(User::where('email', 'sales@erp.local')->first())->get("/projects/{$p->id}")->assertOk();
        $this->patchJson(route('projects.status', $p), ['status' => 'on_hold'])->assertForbidden();
    }

    public function test_task_add_and_status_toggle(): void
    {
        $p = Project::first();
        $emp = Employee::first();
        $this->actingAs($this->admin())->post(route('project-tasks.store', $p), ['title' => 'Install ridge capping', 'assignee_id' => $emp->id, 'status' => 'todo'])
            ->assertRedirect();
        $task = $p->tasks()->where('title', 'Install ridge capping')->firstOrFail();
        $this->patchJson(route('project-tasks.update', $task), ['status' => 'done'])->assertOk()->assertJson(['status' => 'done']);
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_progress_update_moves_scheduled_project_to_in_progress(): void
    {
        $p = Project::where('status', 'scheduled')->first();
        $this->actingAs($this->admin())->post(route('projects.updates.store', $p), ['note' => 'Started work', 'progress' => 25])->assertRedirect();
        $p->refresh();
        $this->assertSame(25, $p->progress);
        $this->assertSame('in_progress', $p->status);
        $this->assertSame(1, $p->updates()->where('note', 'Started work')->count());
    }

    public function test_cost_add_feeds_costing_and_stock_costs_cannot_be_deleted(): void
    {
        $p = Project::where('status', 'in_progress')->first();
        $before = (float) $p->costs()->sum('amount');
        $this->actingAs($this->admin())->post(route('project-costs.store', $p), [
            'cost_date' => today()->toDateString(), 'category' => 'Subcon', 'description' => 'Subcon gutter crew', 'amount' => '1234.50',
        ])->assertRedirect();
        $this->assertEqualsWithDelta($before + 1234.50, (float) $p->costs()->sum('amount'), 0.001);

        // Costing summary by category contains the new Subcon amount.
        $res = $this->get('/costing/summary?group=category&from='.today()->startOfYear()->toDateString().'&to='.today()->toDateString())->assertOk();
        $subcon = (float) ProjectCost::where('category', 'Subcon')->whereYear('cost_date', today()->year)->sum('amount');
        $res->assertSee(number_format($subcon, 2));

        // Stock-generated material line is protected.
        $stockCost = ProjectCost::whereNotNull('stock_movement_id')->first();
        $this->delete(route('project-costs.destroy', $stockCost))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($stockCost->fresh());

        // A manual line can be deleted.
        $manual = $p->costs()->where('description', 'Subcon gutter crew')->first();
        $this->delete(route('project-costs.destroy', $manual))->assertRedirect();
        $this->assertNull($manual->fresh());
    }

    public function test_project_with_costs_cannot_be_deleted(): void
    {
        $p = Project::has('costs')->first();
        $this->actingAs($this->admin())->delete(route('projects.destroy', $p))->assertSessionHas('error');
        $this->assertNotNull($p->fresh());
    }

    public function test_warranty_end_date_is_start_plus_years(): void
    {
        $p = Project::first();
        $this->actingAs($this->admin())->post('/warranties', [
            'customer_id' => $p->customer_id, 'project_id' => $p->id, 'service' => 'Roof Coating', 'start_date' => '2026-02-28', 'years' => 3,
        ])->assertRedirect();
        $w = Warranty::where('start_date', 'like', '2026-02-28%')->latest('id')->firstOrFail();
        $this->assertSame('2029-02-28', $w->end_date->toDateString());
        $this->assertMatchesRegularExpression('#^WR-\d{4}/\d{4}$#', $w->doc_no);
        $this->get(route('warranties.print', $w))->assertOk()->assertSee('WARRANTY CERTIFICATE');
    }

    public function test_service_request_create_auto_schedules_and_done_needs_resolution(): void
    {
        $c = Customer::first();
        $emp = Employee::first();
        $this->actingAs($this->admin())->post('/service-requests', [
            'customer_id' => $c->id, 'type' => 'repair', 'title' => 'Fix flashing', 'priority' => 'high',
            'scheduled_at' => today()->addDay()->setTime(9, 30)->format('Y-m-d\TH:i'), 'duration_minutes' => 90,
            'assigned_to' => $emp->id, 'status' => 'open',
        ])->assertRedirect();
        $sr = ServiceRequest::where('title', 'Fix flashing')->firstOrFail();
        $this->assertSame('scheduled', $sr->status);
        $this->assertMatchesRegularExpression('#^SR-\d{4}/\d{4}$#', $sr->doc_no);

        $this->patch(route('service-requests.status', $sr), ['status' => 'done'])->assertSessionHasErrors('resolution');
        $this->patch(route('service-requests.status', $sr), ['status' => 'done', 'resolution' => 'Replaced flashing, water tested'])->assertSessionHasNoErrors();
        $this->assertSame('done', $sr->fresh()->status);

        $this->get('/service-requests/calendar?week='.today()->addDay()->toDateString())->assertOk()->assertSee('Fix flashing');
    }

    public function test_stock_issued_to_project_shows_on_project_page(): void
    {
        $p = Project::where('status', 'in_progress')->first();
        $item = Item::where('sku', 'SEAL-PU')->first();
        $wh = Warehouse::first();
        $this->actingAs($this->admin());
        app(StockService::class)->move($item->id, $wh->id, -1, 'project', ['project_id' => $p->id]);
        $this->get(route('projects.show', $p))->assertOk()->assertSee('PU SEALANT 600ML');
    }
}
