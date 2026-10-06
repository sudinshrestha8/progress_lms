<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('super_admin.name');
        $email = config('super_admin.email');
        $password = config('super_admin.password');

        if (! is_string($password) || $password === '') {
            throw new InvalidArgumentException('Set SUPER_ADMIN_PASSWORD before running the database seeder.');
        }

        $roleId = DB::table('roles')->where('role_code', 'super_admin')->value('role_id');

        if ($roleId === null) {
            throw new RuntimeException('The super_admin role does not exist. Run the migrations before seeding.');
        }

        DB::transaction(function () use ($name, $email, $password, $roleId): void {
            $userId = DB::table('users')->where('email', $email)->value('user_id');

            if ($userId === null) {
                $userId = DB::table('users')->insertGetId([
                    'email' => $email,
                    'password_hash' => Hash::make($password),
                    'full_name' => $name,
                    'status' => 'ACTIVE',
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'user_id');
            }

            DB::table('user_roles')->insertOrIgnore([
                'user_id' => $userId,
                'role_id' => $roleId,
                'granted_at' => now(),
            ]);
        });
    }
}