<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->staff = User::where('email', 'staff@erp.local')->firstOrFail();
    }

    private function profileData(array $over = []): array
    {
        return array_merge(['name' => 'Lee Jun Hao', 'email' => 'staff@erp.local', 'locale' => 'en', 'phone' => '012-345 6789',
            'nickname' => 'jh', 'personal_email' => 'jh@example.com', 'address' => 'Puchong', 'emergency_contact' => 'Mum 012-111'], $over);
    }

    public function test_staff_updates_own_contact_details_but_not_salary(): void
    {
        $this->actingAs($this->staff)->put(route('profile.update'), $this->profileData(['basic_salary' => 99999]))->assertSessionHas('ok');
        $emp = $this->staff->employee->fresh();
        $this->assertSame('012-345 6789', $emp->phone);
        $this->assertSame('JH', $emp->nickname);
        $this->assertSame('Mum 012-111', $emp->emergency_contact);
        $this->assertNotEquals(99999, (float) $emp->basic_salary);
    }

    public function test_changing_login_email_needs_current_password(): void
    {
        $this->actingAs($this->staff)->put(route('profile.update'), $this->profileData(['email' => 'new@erp.local']))->assertSessionHasErrors('current_password');
        $this->actingAs($this->staff)->put(route('profile.update'), $this->profileData(['email' => 'new@erp.local', 'current_password' => 'password']))->assertSessionHas('ok');
        $this->assertSame('new@erp.local', $this->staff->fresh()->email);
    }

    public function test_password_change_rules(): void
    {
        $this->actingAs($this->staff)->put(route('profile.password'), ['current_password' => 'wrong', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->actingAs($this->staff)->put(route('profile.password'), ['current_password' => 'password', 'password' => 'short1', 'password_confirmation' => 'short1'])
            ->assertSessionHasErrorsIn('password', 'password');
        $this->actingAs($this->staff)->put(route('profile.password'), ['current_password' => 'password', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123'])
            ->assertSessionHas('ok');
        $this->assertTrue(Hash::check('Newpass123', $this->staff->fresh()->password));
    }

    public function test_language_switch_is_saved_and_applied(): void
    {
        $this->actingAs($this->staff)->post(route('locale', 'zh_CN'))->assertRedirect();
        $this->assertSame('zh_CN', $this->staff->fresh()->locale);
        $this->actingAs($this->staff->fresh())->get(route('dashboard'))->assertOk()->assertSee('仪表板')->assertSee('上班打卡');
        $this->actingAs($this->staff->fresh())->post(route('locale', 'en'));
        $this->actingAs($this->staff->fresh())->get(route('dashboard'))->assertSee('Clock In')->assertDontSee('仪表板');
        $this->post(route('locale', 'xx'))->assertNotFound();
    }

    public function test_guest_language_choice_carries_into_the_account(): void
    {
        $this->post(route('locale', 'zh_CN'));
        $this->get(route('login'))->assertSee('欢迎回来');
        $this->post(route('login'), ['email' => 'staff@erp.local', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertSame('zh_CN', $this->staff->fresh()->locale);
    }

    public function test_chinese_validation_messages(): void
    {
        $this->actingAs($this->staff)->post(route('locale', 'zh_CN'));
        $this->actingAs($this->staff->fresh())->put(route('profile.update'), $this->profileData(['name' => '', 'locale' => 'zh_CN']))
            ->assertSessionHasErrors(['name' => '名称不能为空。']);
    }
}
