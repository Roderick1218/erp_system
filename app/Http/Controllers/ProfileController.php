<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Self-service: every user edits their own login, contact details, password and language. */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('employee.department', 'employee.branch', 'branch');

        return view('profile.edit', ['user' => $user, 'employee' => $user->employee]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($request->input('email') !== $user->email), 'nullable', 'current_password'],
            'locale' => ['required', Rule::in(array_keys(SetLocale::LOCALES))],
            // Contact details on the linked employee record (HR keeps everything else).
            'nickname' => 'nullable|string|max:64',
            'phone' => 'nullable|string|max:32',
            'personal_email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'emergency_contact' => 'nullable|string|max:255',
        ], ['current_password.required' => __('Enter your current password to change your login email.')]);

        DB::transaction(function () use ($user, $data) {
            $user->update(['name' => $data['name'], 'email' => $data['email'], 'locale' => $data['locale']]);
            $user->employee?->update([
                'nickname' => $data['nickname'] ? mb_strtoupper($data['nickname']) : null,
                'phone' => $data['phone'],
                'email' => $data['personal_email'],
                'address' => $data['address'],
                'emergency_contact' => $data['emergency_contact'],
            ]);
        });
        app()->setLocale($data['locale']);

        return back()->with('ok', __('Profile updated.'));
    }

    public function password(Request $request)
    {
        $data = $request->validateWithBag('password', [
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ]);
        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();
        activity('User')->causedBy($request->user())->performedOn($request->user())->log('changed own password');

        return back()->with('ok', __('Password changed. Use the new password next time you sign in.'));
    }

    /** Language switch — works for guests (login page) and signed-in users. */
    public function locale(Request $request, string $locale)
    {
        abort_unless(array_key_exists($locale, SetLocale::LOCALES), 404);
        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return back();
    }
}
