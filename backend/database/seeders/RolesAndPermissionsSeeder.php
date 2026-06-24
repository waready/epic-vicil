<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'manage.users',
            'manage.catalogs',
            'manage.accreditation',
            'view.evidences',
            'create.evidences',
            'review.evidences',
            'validate.evidences',
            'approve.evidences',
            'export.evidences',
            'view.dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super_admin' => $permissions,
            'admin_facultad' => [
                'manage.catalogs',
                'manage.accreditation',
                'view.evidences',
                'review.evidences',
                'validate.evidences',
                'export.evidences',
                'view.dashboard',
            ],
            'director_programa' => [
                'view.evidences',
                'review.evidences',
                'validate.evidences',
                'approve.evidences',
                'export.evidences',
                'view.dashboard',
            ],
            'coordinador_acreditacion' => [
                'manage.accreditation',
                'view.evidences',
                'create.evidences',
                'review.evidences',
                'validate.evidences',
                'export.evidences',
                'view.dashboard',
            ],
            'comite_calidad' => [
                'view.evidences',
                'review.evidences',
                'validate.evidences',
                'view.dashboard',
            ],
            'docente' => [
                'view.evidences',
                'create.evidences',
                'view.dashboard',
            ],
            'responsable_laboratorio' => [
                'view.evidences',
                'create.evidences',
                'view.dashboard',
            ],
            'auditor_interno' => [
                'view.evidences',
                'review.evidences',
                'view.dashboard',
            ],
            'consulta' => [
                'view.evidences',
                'view.dashboard',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
