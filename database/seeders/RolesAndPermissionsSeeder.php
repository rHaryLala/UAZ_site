<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const SUPER_ADMIN = 'super-admin';

    public const EDITOR = 'editor';

    public const CONTRIBUTOR = 'contributor';

    /** @var array<string, list<string>> rôle => permissions (le super-admin passe par Gate::before) */
    public const MATRIX = [
        self::EDITOR => [
            'content.view', 'content.create', 'content.update', 'content.delete', 'content.publish',
            'media.view', 'media.create', 'media.manage',
            'settings.manage',
            'messages.view', 'messages.export', 'messages.delete',
        ],
        self::CONTRIBUTOR => [
            // Brouillons uniquement : la politique limite la modification à ses propres brouillons.
            'content.view', 'content.create',
            'media.view', 'media.create',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::MATRIX)->flatten()->push('users.manage')->unique();
        $all->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::findOrCreate(self::SUPER_ADMIN, 'web');

        foreach (self::MATRIX as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
