<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $permissions = Permission::query()
            ->orderBy('module')
            ->orderBy('permission_code')
            ->get(['permission_id', 'permission_code', 'module', 'description']);

        $roles = Role::query()
            ->with(['permissions:permission_id'])
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('role_name')
            ->get()
            ->map(fn (Role $role): array => [
                'role_id' => $role->role_id,
                'role_code' => $role->role_code,
                'role_name' => $role->role_name,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions' => $role->role_code === 'super_admin'
                    ? $permissions->pluck('permission_id')->all()
                    : $role->permissions->pluck('permission_id')->all(),
            ]);

        return Inertia::render('admin/RolesPermissions', [
            'accessControl' => [
                'user' => [
                    'name' => $request->user()->name,
                    'role' => 'Super Administrator',
                ],
                'roles' => $roles,
                'permissions' => $permissions,
                'summary' => [
                    'roles' => $roles->count(),
                    'customRoles' => $roles->where('is_system', false)->count(),
                    'permissions' => $permissions->count(),
                    'assignedUsers' => DB::table('user_roles')->distinct()->count('user_id'),
                ],
            ],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $role = Role::query()->create([
                'role_code' => $validated['role_code'],
                'role_name' => $validated['role_name'],
                'description' => $validated['description'] ?? null,
                'is_system' => false,
            ]);

            $role->permissions()->sync($validated['permissions']);
        });

        return back()->with('success', 'Role created successfully.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        if ($role->role_code === 'super_admin') {
            return back()->withErrors(['role' => 'Super Administrator always has full access.']);
        }

        DB::transaction(function () use ($role, $validated): void {
            if (! $role->is_system) {
                $role->update([
                    'role_name' => $validated['role_name'],
                    'description' => $validated['description'] ?? null,
                ]);
            }

            $role->permissions()->sync($validated['permissions']);
        });

        return back()->with('success', 'Role permissions updated.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Remove all assigned users before deleting this role.']);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }
}
