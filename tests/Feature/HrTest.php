<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\User;
use App\Services\StatutoryCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_leave_days_skip_sundays_and_public_holidays(): void
    {
        // Mon 21 Dec – Sun 27 Dec 2026: 6 non-Sundays, minus Christmas (Fri 25) = 5
        $this->assertSame(5.0, (float) LeaveRequest::countDays(Carbon::parse('2026-12-21'), Carbon::parse('2026-12-27')));
        $this->assertSame(0.5, (float) LeaveRequest::countDays(Carbon::parse('2026-12-22'), Carbon::parse('2026-12-22'), null, true));
    }

    public function test_staff_applies_for_leave_and_overlap_is_rejected(): void
    {
        $staff = $this->user('staff@erp.local');
        $al = LeaveType::where('code', 'AL')->value('id');

        $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $al, 'start_date' => '2026-12-21', 'end_date' => '2026-12-27', 'reason' => 'Year-end trip',
        ])->assertRedirect(route('leave.index'))->assertSessionHasNoErrors();

        $req = LeaveRequest::where('employee_id', $staff->employee_id)->latest('id')->first();
        $this->assertSame('pending', $req->status);
        $this->assertEquals(5, (float) $req->days);

        $this->actingAs($staff)->post(route('leave.store'), [
            'leave_type_id' => $al, 'start_date' => '2026-12-24', 'end_date' => '2026-12-24', 'reason' => 'Overlap',
        ])->assertSessionHasErrors('start_date');
        $this->assertSame(0, LeaveRequest::where('employee_id', $staff->employee_id)->where('reason', 'Overlap')->count());
    }

    public function test_hr_approves_and_rejects_leave(): void
    {
        $hr = $this->user('hr@erp.local');
        $pending = LeaveRequest::where('status', 'pending')->where('employee_id', '!=', $hr->employee_id)->get();
        [$a, $b] = [$pending[0], $pending[1]];

        $this->actingAs($hr)->post(route('leave.approve', $a))->assertRedirect();
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame($hr->id, $a->fresh()->reviewed_by);

        $this->actingAs($hr)->post(route('leave.reject', $b), [])->assertSessionHasErrors('review_note');
        $this->actingAs($hr)->post(route('leave.reject', $b), ['review_note' => 'Site handover that week'])->assertRedirect();
        $this->assertSame('rejected', $b->fresh()->status);

        // Staff cannot reach the approval queue
        $this->actingAs($this->user('staff@erp.local'))->get(route('leave.approvals'))->assertForbidden();
    }

    public function test_payroll_run_numbers_for_a_known_employee(): void
    {
        $admin = $this->user('admin@erp.local');
        $this->actingAs($admin)->post(route('payroll.store'), ['period' => '2026-11'])->assertRedirect()->assertSessionHasNoErrors();

        $emp = Employee::where('emp_no', 'E0002')->first(); // basic 3,200 + allowance 200, Malaysian, < 60
        $slip = Payslip::where('employee_id', $emp->id)->firstOrFail();
        $this->assertEquals(3400.00, (float) $slip->gross);
        $this->assertEquals(374.00, (float) $slip->epf_employee);   // 11%
        $this->assertEquals(442.00, (float) $slip->epf_employer);   // 13% (≤ RM5,000)
        $this->assertEquals(17.00, (float) $slip->socso_employee);  // 0.5%
        $this->assertEquals(59.50, (float) $slip->socso_employer);  // 1.75%
        $this->assertEquals(6.80, (float) $slip->eis_employee);     // 0.2%
        $this->assertEquals(3002.20, (float) $slip->net_pay);

        // Overtime edit recomputes; duplicate month rejected; finalize locks edits.
        $this->actingAs($admin)->put(route('payslips.update', $slip), ['overtime' => 100, 'commission' => 0, 'allowance' => 200, 'other_deduction' => 0])->assertRedirect();
        $this->assertEquals(3500.00, (float) $slip->fresh()->gross);
        $this->assertEquals(385.00, (float) $slip->fresh()->epf_employee);
        $this->actingAs($admin)->post(route('payroll.store'), ['period' => '2026-11'])->assertSessionHasErrors('period');
        $this->actingAs($admin)->post(route('payroll.finalize', $slip->run))->assertRedirect();
        $this->actingAs($admin)->put(route('payslips.update', $slip), ['overtime' => 999])->assertStatus(422);
    }

    public function test_statutory_calculator_rules(): void
    {
        $calc = new StatutoryCalculator;
        $local = new Employee(['nationality' => 'MALAYSIA', 'employment_type' => 'permanent', 'dob' => '1990-01-01']);
        $high = $calc->calculate($local, 7000, Carbon::parse('2026-11-30'));
        $this->assertEquals(770.00, $high['epf_employee']);
        $this->assertEquals(840.00, $high['epf_employer']);   // 12% above RM5,000
        $this->assertEquals(30.00, $high['socso_employee']);  // capped at RM6,000
        $this->assertEquals(12.00, $high['eis_employee']);

        $foreign = new Employee(['nationality' => 'BANGLADESH', 'employment_type' => 'foreign_worker', 'dob' => '1995-01-01']);
        $f = $calc->calculate($foreign, 2000, Carbon::parse('2026-11-30'));
        $this->assertEquals(40.00, $f['epf_employee']);
        $this->assertEquals(0.0, $f['eis_employee']);

        $senior = new Employee(['nationality' => 'MALAYSIA', 'employment_type' => 'permanent', 'dob' => '1960-01-01']);
        $s = $calc->calculate($senior, 3000, Carbon::parse('2026-11-30'));
        $this->assertEquals(0.0, $s['socso_employee']);
        $this->assertEquals(37.50, $s['socso_employer']);
        $this->assertEquals(0.0, $s['eis_employer']);
    }

    public function test_payslip_is_only_visible_to_owner_or_payroll_manager(): void
    {
        $this->actingAs($this->user('admin@erp.local'))->post(route('payroll.store'), ['period' => '2026-11']);
        $staff = $this->user('staff@erp.local');
        $other = Payslip::where('employee_id', '!=', $staff->employee_id)->firstOrFail();
        $own = Payslip::where('employee_id', $staff->employee_id)->firstOrFail();

        $this->actingAs($staff)->get(route('payslips.show', $other))->assertForbidden();
        $this->actingAs($staff)->get(route('payslips.show', $own))->assertOk()->assertSee('PAYSLIP');
        $this->actingAs($this->user('hr@erp.local'))->get(route('payslips.show', $other))->assertOk();
    }

    public function test_role_permission_update_takes_effect(): void
    {
        $staff = $this->user('staff@erp.local');
        $this->actingAs($staff)->get(route('customers.index'))->assertForbidden();

        $role = Role::findByName('Staff');
        $this->actingAs($this->user('admin@erp.local'))->put(route('roles.update', $role), ['name' => 'Staff', 'permissions' => ['customers.view']])->assertRedirect();

        $this->actingAs($staff->fresh())->get(route('customers.index'))->assertOk();
        $this->actingAs($this->user('admin@erp.local'))->put(route('roles.update', $role), ['name' => 'Staff', 'permissions' => ['not.a.permission']])->assertSessionHasErrors();
        $this->actingAs($this->user('admin@erp.local'))->delete(route('roles.destroy', $role))->assertRedirect();
        $this->assertNotNull(Role::where('name', 'Staff')->first()); // still assigned → not deleted
    }

    public function test_user_cannot_deactivate_or_delete_self(): void
    {
        $admin = $this->user('admin@erp.local');
        $this->actingAs($admin)->put(route('users.update', $admin), ['toggle_active' => 1])->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertNull($admin->fresh()->deleted_at);

        $staff = $this->user('staff@erp.local');
        $this->actingAs($admin)->put(route('users.update', $staff), ['toggle_active' => 1]);
        $this->assertFalse($staff->fresh()->is_active);
    }

    public function test_clock_in_out_and_late_flag(): void
    {
        $staff = $this->user('staff@erp.local');

        $this->travelTo(Carbon::parse('2026-11-02 08:20:00'));
        $this->actingAs($staff)->post(route('attendance.clock'))->assertRedirect();
        $row = Attendance::where('employee_id', $staff->employee_id)->whereDate('work_date', '2026-11-02')->first();
        $this->assertNotNull($row->clock_in);
        $this->assertFalse($row->is_late);

        $this->travelTo(Carbon::parse('2026-11-02 17:45:00'));
        $this->actingAs($staff)->post(route('attendance.clock'))->assertRedirect();
        $this->assertNotNull($row->fresh()->clock_out);
        $this->assertSame(1, Attendance::where('employee_id', $staff->employee_id)->whereDate('work_date', '2026-11-02')->count());

        $this->travelTo(Carbon::parse('2026-11-03 08:55:00')); // after 08:30 + 10 min grace
        $this->actingAs($staff)->post(route('attendance.clock'));
        $this->assertTrue(Attendance::where('employee_id', $staff->employee_id)->whereDate('work_date', '2026-11-03')->first()->is_late);

        // HR fixes the late record; is_late is recomputed
        $late = Attendance::where('employee_id', $staff->employee_id)->whereDate('work_date', '2026-11-03')->first();
        $this->actingAs($this->user('hr@erp.local'))->put(route('attendance.update', $late), ['clock_in' => '08:30', 'clock_out' => '17:30', 'remarks' => 'Site visit'])->assertRedirect();
        $this->assertFalse($late->fresh()->is_late);
    }
}
