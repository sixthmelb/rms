<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Simplified permissions yang workflow-focused
        $permissions = [
            'manage_own_requests',      // create, view, edit own requests
            'approve_section_requests', // approve as section head
            'approve_scm_requests',     // approve as scm head  
            'approve_final_requests',   // approve as pjo
            'view_all_requests',        // admin/pjo privilege
            'manage_system',            // admin only
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles - SIMPLE & EFFECTIVE
        $this->assignRolePermissions();
    }

    private function assignRolePermissions()
    {
        // ADMIN - full access
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());

        // USER/REQUESTER - own requests only
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->givePermissionTo(['manage_own_requests']);

        // SECTION HEAD - own + section approval
        $sectionHead = Role::firstOrCreate(['name' => 'section_head']);
        $sectionHead->givePermissionTo([
            'manage_own_requests',
            'approve_section_requests'
        ]);

        // SCM HEAD - own + scm approval
        $scmHead = Role::firstOrCreate(['name' => 'scm_head']);
        $scmHead->givePermissionTo([
            'manage_own_requests',
            'approve_scm_requests'
        ]);

        // PJO - own + final approval + view all
        $pjo = Role::firstOrCreate(['name' => 'pjo']);
        $pjo->givePermissionTo([
            'manage_own_requests',
            'approve_final_requests',
            'view_all_requests'
        ]);
    }
}