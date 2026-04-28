<?php

namespace Modules\Crm\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CrmPermissionSeeder extends Seeder
{
    public function run()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'show_crm_reports',
            'access_crm',
            'manage_crm_permissions',
            'view_all_branches',
            // Leads
            'create_crm_leads', 'show_crm_leads', 'edit_crm_leads', 'delete_crm_leads', 'comment_crm_leads', 'convert_crm_leads',
            // Service Orders
            'create_crm_service_orders', 'show_crm_service_orders', 'edit_crm_service_orders', 'delete_crm_service_orders',
            'assign_crm_service_orders',
            // Photos
            'upload_crm_photos', 'delete_crm_photos',
            // Warranty
            'show_crm_warranties', 'upsert_crm_warranties',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Ensure operational roles exist
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $administrator = Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $sales = Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
        $technician = Role::firstOrCreate(['name' => 'Technician', 'guard_name' => 'web']);
        $technicianLeader = Role::firstOrCreate(['name' => 'Technician Leader', 'guard_name' => 'web']);

        // Grant: Admin and Administrator get all CRM perms
        $superAdmin->givePermissionTo($permissions);
        $admin->givePermissionTo($permissions);
        $administrator->givePermissionTo($permissions);

        // Reports access for Admin + Manager
        if (!$manager->hasPermissionTo('show_crm_reports')) { $manager->givePermissionTo('show_crm_reports'); }

        foreach ([$manager, $sales, $technician, $technicianLeader] as $role) {
            if (!$role->hasPermissionTo('access_crm')) { $role->givePermissionTo('access_crm'); }
        }

        // Comments for Admin (covered above), Manager, Sales, Technician
        foreach (['comment_crm_leads'] as $p) {
            foreach ([$manager, $sales, $technician] as $role) {
                if (!$role->hasPermissionTo($p)) { $role->givePermissionTo($p); }
            }
        }

        // Assigned technicians need these for their task list and SPK evidence flow.
        // Controllers keep technician access limited to service orders assigned to the logged-in user.
        foreach (['show_crm_service_orders', 'upload_crm_photos', 'show_crm_warranties', 'upsert_crm_warranties'] as $p) {
            if (!$technician->hasPermissionTo($p)) { $technician->givePermissionTo($p); }
        }

        // Technician Leader: same as technician + can assign technicians to service orders.
        // This permission is what grants access to the "Kelola PK" tab in the mobile app.
        foreach (['show_crm_service_orders', 'assign_crm_service_orders', 'upload_crm_photos', 'show_crm_warranties', 'upsert_crm_warranties'] as $p) {
            if (!$technicianLeader->hasPermissionTo($p)) { $technicianLeader->givePermissionTo($p); }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
