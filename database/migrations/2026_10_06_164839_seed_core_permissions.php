<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            ['dashboard.view', 'Dashboard', 'View institutional dashboard metrics'],
            ['users.view', 'Users', 'View user accounts and profiles'],
            ['users.create', 'Users', 'Create user accounts'],
            ['users.update', 'Users', 'Edit user accounts and profiles'],
            ['users.suspend', 'Users', 'Suspend or restore user access'],
            ['academics.view', 'Academics', 'View academic structures and terms'],
            ['academics.manage', 'Academics', 'Manage departments, programs, batches, and terms'],
            ['classes.view', 'Classes', 'View class and enrollment records'],
            ['classes.manage', 'Classes', 'Create and manage class delivery'],
            ['assessments.view', 'Assessments', 'View assessments and question banks'],
            ['assessments.manage', 'Assessments', 'Create and publish assessments'],
            ['submissions.view', 'Submissions', 'View student submissions'],
            ['submissions.grade', 'Submissions', 'Grade and publish submission results'],
            ['reports.view', 'Reports', 'View institutional reports'],
            ['reports.export', 'Reports', 'Export institutional reports'],
            ['announcements.manage', 'Communication', 'Create and publish announcements'],
            ['activity_logs.view', 'Security', 'View administrative activity logs'],
            ['access_control.manage', 'Security', 'Manage roles and permissions'],
            ['settings.manage', 'Settings', 'Manage institution and integration settings'],
        ];

        DB::table('permissions')->upsert(
            array_map(fn (array $permission): array => [
                'permission_code' => $permission[0],
                'module' => $permission[1],
                'description' => $permission[2],
                'created_at' => now(),
            ], $definitions),
            ['permission_code'],
            ['module', 'description'],
        );

        $permissionIds = DB::table('permissions')->pluck('permission_id', 'permission_code');
        $roleIds = DB::table('roles')->pluck('role_id', 'role_code');
        $grants = [
            'super_admin' => array_keys($permissionIds->all()),
            'admin' => [
                'dashboard.view', 'users.view', 'users.create', 'users.update', 'users.suspend',
                'academics.view', 'academics.manage', 'classes.view', 'classes.manage',
                'assessments.view', 'reports.view', 'reports.export', 'announcements.manage',
                'activity_logs.view', 'settings.manage',
            ],
            'teacher' => [
                'dashboard.view', 'academics.view', 'classes.view', 'assessments.view',
                'assessments.manage', 'submissions.view', 'submissions.grade', 'reports.view',
            ],
            'student' => ['dashboard.view', 'classes.view', 'assessments.view', 'submissions.view'],
        ];

        foreach ($grants as $roleCode => $codes) {
            $roleId = $roleIds->get($roleCode);

            if ($roleId === null) {
                continue;
            }

            DB::table('role_permissions')->insertOrIgnore(array_map(
                fn (string $code): array => [
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds->get($code),
                    'created_at' => now(),
                ],
                $codes,
            ));
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('permission_code', [
            'dashboard.view', 'users.view', 'users.create', 'users.update', 'users.suspend',
            'academics.view', 'academics.manage', 'classes.view', 'classes.manage',
            'assessments.view', 'assessments.manage', 'submissions.view', 'submissions.grade',
            'reports.view', 'reports.export', 'announcements.manage', 'activity_logs.view',
            'access_control.manage', 'settings.manage',
        ])->delete();
    }
};
