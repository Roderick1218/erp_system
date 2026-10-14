<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail());
    }

    public function test_salesman_can_create_customer_with_running_code(): void
    {
        $last = Customer::orderByDesc('code')->value('code');

        $this->as('sales@erp.local')->post(route('customers.store'), [
            'name' => 'Mr Test Roof', 'type' => 'individual', 'phone' => '+60120000001', 'status' => 'active',
        ])->assertRedirect();

        $c = Customer::where('phone', '+60120000001')->firstOrFail();
        $this->assertSame(sprintf('C%05d', (int) substr($last, 1) + 1), $c->code);
        $this->assertNotNull($c->created_by);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $existing = Customer::first();
        $this->as('sales@erp.local')->post(route('customers.store'), [
            'name' => 'Dup', 'type' => 'individual', 'phone' => $existing->phone, 'status' => 'active',
        ])->assertSessionHasErrors('phone');
    }

    public function test_customer_with_projects_cannot_be_deleted(): void
    {
        $c = Customer::has('projects')->firstOrFail();
        $this->as('admin@erp.local')->delete(route('customers.destroy', $c))->assertSessionHas('error');
        $this->assertNotSoftDeleted($c);
    }

    public function test_filters_and_export(): void
    {
        $this->as('admin@erp.local')->get(route('customers.index', ['state' => 'Johor']))
            ->assertOk()->assertSee('Johor')->assertDontSee('Pulau Pinang</span>', false);
        $this->as('admin@erp.local')->get(route('customers.export'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_staff_without_permission_gets_403(): void
    {
        $this->as('staff@erp.local')->get(route('customers.index'))->assertForbidden();
        $this->as('staff@erp.local')->get(route('dashboard'))->assertOk();
    }

    public function test_search_respects_permissions(): void
    {
        $name = Customer::first()->name;
        $this->as('admin@erp.local')->getJson(route('search', ['q' => substr($name, 0, 5)]))->assertOk()->assertJsonFragment(['type' => 'Customers']);
        $this->as('staff@erp.local')->getJson(route('search', ['q' => substr($name, 0, 5)]))->assertOk()->assertJsonMissing(['type' => 'Customers']);
    }

    public function test_clock_in_then_out(): void
    {
        $this->as('staff@erp.local')->post(route('attendance.clock'))->assertSessionHas('ok');
        $this->as('staff@erp.local')->post(route('attendance.clock'))->assertSessionHas('ok');
        $row = User::where('email', 'staff@erp.local')->first()->employee->attendances()->whereDate('work_date', today())->first();
        $this->assertNotNull($row->clock_in);
        $this->assertNotNull($row->clock_out);
        $this->as('staff@erp.local')->post(route('attendance.clock'))->assertSessionHasErrors('clock');
    }
}
