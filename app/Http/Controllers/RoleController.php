<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index()
    {
        // Make sure every permission defined in config exists (new modules add new keys).
        foreach (array_keys(config('erp.permissions')) as $p) {
            Permission::findOrCreate($p, 'web');
        }

        return view('roles.index', [
            'roles' => Role::with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissions' => config('erp.permissions'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);
        $this->flush();

        return back()->with('ok', __('Role “:name” created.', ['name' => __($role->name)]));
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions']);
        $this->flush();

        return back()->with('ok', __('Role “:name” updated — :n permission(s).', ['name' => __($role->name), 'n' => count($data['permissions'])]));
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->with('error', __('“:name” is assigned to :n user(s) — reassign them first.', ['name' => __($role->name), 'n' => $role->users()->count()]));
        }
        $role->delete();
        $this->flush();

        return back()->with('ok', __('Role “:name” deleted.', ['name' => __($role->name)]));
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => 'array',
            'permissions.*' => Rule::in(array_keys(config('erp.permissions'))),
        ]);
        $data['permissions'] ??= [];

        return $data;
    }

    private function flush(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
