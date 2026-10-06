<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function superAdministrator(): User
{
    $user = User::factory()->create();
    $roleId = DB::table('roles')->where('role_code', 'super_admin')->value('role_id');

    DB::table('user_roles')->insert([
        'user_id' => $user->getKey(),
        'role_id' => $roleId,
        'granted_at' => now(),
    ]);

    return $user;
}

test('only super administrators can open access control', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.roles.index'))
        ->assertForbidden();

    $this->actingAs(superAdministrator())
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/RolesPermissions')
            ->has('accessControl.roles', 4)
            ->has('accessControl.permissions', 19)
            ->where('accessControl.summary.customRoles', 0)
        );
});

test('super administrators can create a role with permission grants', function () {
    $permissionIds = DB::table('permissions')->limit(2)->pluck('permission_id')->all();

    $this->actingAs(superAdministrator())
        ->post(route('admin.roles.store'), [
            'role_code' => 'content_moderator',
            'role_name' => 'Content Moderator',
            'description' => 'Reviews learning content.',
            'permissions' => $permissionIds,
        ])
        ->assertRedirect();

    $roleId = DB::table('roles')->where('role_code', 'content_moderator')->value('role_id');

    expect($roleId)->not->toBeNull();
    expect(DB::table('role_permissions')->where('role_id', $roleId)->count())->toBe(2);
});

test('super administrators can update a custom role permission matrix', function () {
    $roleId = DB::table('roles')->insertGetId([
        'role_code' => 'auditor',
        'role_name' => 'Auditor',
        'description' => null,
        'is_system' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ], 'role_id');
    $permissionId = DB::table('permissions')->where('permission_code', 'reports.view')->value('permission_id');

    $this->actingAs(superAdministrator())
        ->put(route('admin.roles.update', $roleId), [
            'role_name' => 'Academic Auditor',
            'description' => 'Read-only reporting role.',
            'permissions' => [$permissionId],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('roles', [
        'role_id' => $roleId,
        'role_name' => 'Academic Auditor',
    ]);
    $this->assertDatabaseHas('role_permissions', [
        'role_id' => $roleId,
        'permission_id' => $permissionId,
    ]);
});

test('system roles cannot be deleted', function () {
    $roleId = DB::table('roles')->where('role_code', 'admin')->value('role_id');

    $this->actingAs(superAdministrator())
        ->delete(route('admin.roles.destroy', $roleId))
        ->assertSessionHasErrors('role');

    $this->assertDatabaseHas('roles', ['role_id' => $roleId]);
});
