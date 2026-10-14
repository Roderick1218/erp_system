<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $rows = User::with(['roles', 'employee', 'branch'])
            ->when($request->input('q'), fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")))
            ->when($request->input('role'), fn ($q, $v) => $q->role($v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('user_type', $v))
            ->when($request->input('status') !== null && $request->input('status') !== '', fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->orderByDesc('is_active')->orderBy('name')->paginate(25)->withQueryString();

        return view('users.index', ['rows' => $rows, 'roles' => Role::orderBy('name')->pluck('name')]);
    }

    public function create(Request $request)
    {
        $user = new User(['user_type' => 'staff', 'is_active' => true, 'branch_id' => $request->user()->branch_id]);
        if ($emp = Employee::find($request->input('employee_id'))) {
            $user->fill(['employee_id' => $emp->id, 'name' => ucwords(strtolower($emp->name)), 'email' => $emp->email, 'branch_id' => $emp->branch_id]);
        }

        return view('users.form', ['u' => $user, ...$this->lookups($user)]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data['user']);
        $user->syncRoles($data['role'] ? [$data['role']] : []);

        return redirect()->route('users.index')->with('ok', __('User :email created.', ['email' => $user->email]));
    }

    public function edit(User $user)
    {
        return view('users.form', ['u' => $user, ...$this->lookups($user)]);
    }

    public function update(Request $request, User $user)
    {
        // Quick active toggle from the list.
        if ($request->has('toggle_active')) {
            if ($user->is($request->user())) {
                return back()->with('error', __('You cannot deactivate your own account.'));
            }
            $user->update(['is_active' => ! $user->is_active]);

            return back()->with('ok', $user->is_active ? __(':name is now active.', ['name' => $user->name]) : __(':name is now inactive.', ['name' => $user->name]));
        }

        $data = $this->validated($request, $user);
        if ($user->is($request->user()) && (! $data['user']['is_active'] || $data['user']['user_type'] !== $user->user_type)) {
            return back()->withInput()->with('error', __('You cannot deactivate your own account or change your own user type.'));
        }
        $user->update($data['user']);
        $user->syncRoles($data['role'] ? [$data['role']] : []);

        return redirect()->route('users.index')->with('ok', __('User :email updated.', ['email' => $user->email]));
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('You cannot delete your own account.'));
        }
        $user->delete();

        return redirect()->route('users.index')->with('ok', __('User :email deleted.', ['email' => $user->email]));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'user_type' => 'required|in:'.implode(',', array_keys(User::TYPES)),
            'role' => 'nullable|exists:roles,name',
            'employee_id' => ['nullable', 'exists:employees,id', Rule::unique('users', 'employee_id')->ignore($user?->id)->whereNull('deleted_at')],
            'branch_id' => 'nullable|exists:branches,id',
        ], ['employee_id.unique' => __('That employee already has a login account.')]);

        $fields = collect($data)->only(['name', 'email', 'user_type', 'employee_id', 'branch_id'])->all();
        $fields['is_active'] = $request->boolean('is_active');
        if (! empty($data['password'])) {
            $fields['password'] = $data['password'];
        }

        return ['user' => $fields, 'role' => $data['role'] ?? null];
    }

    private function lookups(User $user): array
    {
        $linked = User::whereNotNull('employee_id')->where('id', '!=', $user->id ?? 0)->pluck('employee_id');

        return [
            'roles' => Role::orderBy('name')->pluck('name', 'name')->all(),
            'branches' => Branch::orderBy('name')->pluck('legal_name', 'id')->all(),
            'employees' => Employee::active()->whereNotIn('id', $linked)->orderBy('name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->emp_no.' — '.$e->display_name])->all(),
        ];
    }
}
