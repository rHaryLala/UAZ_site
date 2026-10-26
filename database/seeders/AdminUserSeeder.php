<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('uaz.seed_admin.password');

        if (app()->isProduction() && blank($password)) {
            $this->command?->warn('SEED_ADMIN_PASSWORD vide : super-admin non créé en production.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => config('uaz.seed_admin.email')],
            [
                'name' => config('uaz.seed_admin.name'),
                'password' => Hash::make($password ?: 'password'),
            ],
        );

        $user->syncRoles(RolesAndPermissionsSeeder::SUPER_ADMIN);
    }
}
