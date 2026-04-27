<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // IMPORTANT: clear cache supaya permission baru kebaca
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            //User Mangement
            'edit_own_profile',
            'access_user_management',

            //Dashboard
            'show_total_stats',
            'show_month_overview',
            'show_weekly_sales_purchases',
            'show_monthly_cashflow',
            'show_notifications',

            //Products
            'access_products',
            'access_product_accessories',
            'create_products',
            'show_products',
            'edit_products',
            'delete_products',
            'import_products',
            'access_warehouses',

            //Product Categories
            'access_product_categories',

            //Barcode Printing
            'print_barcodes',

            //Adjustments
            'access_adjustments',
            'create_adjustments',
            'show_adjustments',
            'edit_adjustments',
            'delete_adjustments',

            //Quotaions
            'access_quotations',
            'create_quotations',
            'show_quotations',
            'edit_quotations',
            'delete_quotations',

            //Create Sale From Quotation
            'create_quotation_sales',

            //Send Quotation On Email
            'send_quotation_mails',

            //Expenses
            'access_expenses',
            'create_expenses',
            'edit_expenses',
            'delete_expenses',

            //Expense Categories
            'access_expense_categories',

            //Customers
            'access_customers',
            'create_customers',
            'show_customers',
            'edit_customers',
            'update_customers',
            'delete_customers',

            //Branches
            'access_branches',
            'show_branches',
            'create_branch',
            'create_branches',
            'edit_branch',
            'edit_branches',
            'delete_branch',
            'delete_branches',

            //Suppliers
            'access_suppliers',
            'create_suppliers',
            'show_suppliers',
            'edit_suppliers',
            'delete_suppliers',

            //Sales
            'access_sales',
            'create_sales',
            'show_sales',
            'edit_sales',
            'delete_sales',

            //POS Sale
            'create_pos_sales',

            //Sale Payments
            'access_sale_payments',

            //Sale Returns
            'access_sale_returns',
            'create_sale_returns',
            'show_sale_returns',
            'edit_sale_returns',
            'delete_sale_returns',

            //Sale Return Payments
            'access_sale_return_payments',

            //Purchases
            'access_purchases',
            'create_purchase',
            'create_purchases',
            'show_purchases',
            'edit_purchases',
            'delete_purchases',

            // Purchase Deliveries
            'access_purchase_deliveries',
            'create_purchase_deliveries',
            'show_purchase_deliveries',
            'edit_purchase_deliveries',
            'delete_purchase_deliveries',

            // Purchase Orders
            'access_purchase_orders',
            'access_purchase-orders',
            'create_purchase_orders',
            'create_purchase-orders',
            'show_purchase_orders',
            'edit_purchase_orders',
            'delete_purchase_orders',
            'create_purchase_order_purchases',
            'send_purchase_order_mails',

            //Purchase Payments
            'access_purchase_payments',

            //Purchase Returns
            'access_purchase_returns',
            'create_purchase_returns',
            'show_purchase_returns',
            'edit_purchase_returns',
            'delete_purchase_returns',
            'delete_purchase_return',

            //Purchase Return Payments
            'access_purchase_return_payments',

            //Reports
            'access_reports',

            //Currencies
            'access_currencies',
            'create_currencies',
            'edit_currencies',
            'delete_currencies',

            //Settings
            'access_settings',

            // Sale Orders
            'access_sale_orders',
            'create_sale_orders',
            'show_sale_orders',
            'edit_sale_orders',
            'delete_sale_orders',
            'create_sale_invoices',

            // Sale Deliveries
            'access_sale_deliveries',
            'create_sale_deliveries',
            'show_sale_deliveries',
            'edit_sale_deliveries',
            'delete_sale_deliveries',

            // Transfers
            'access_transfers',
            'create_transfers',
            'show_transfers',
            'delete_transfers',
            'print_transfers',
            'cancel_transfers',

            // Inventory / Stock
            'access_inventories',
            'access_invetories',
            'access_mutations',
            'create_mutations',
            'show_mutations',
            'edit_mutations',
            'delete_mutations',
            'delete_inventory',

            'access_racks',
            'create_racks',
            'edit_racks',
            'delete_racks',
            'import_racks',

            // Rack Movements (move stock between racks within same branch)
            'access_rack_movements',
            'create_rack_movements',
            'show_rack_movements',
            'import_opening_stock',
        ];

        // ✅ Permission khusus internal (HPP/Profit)
        // Penting: jangan dimasukin ke $permissions (karena kamu auto-sync semua ke role Admin)
        // Biar yang punya cuma role Administrator.
        $internalPermissions = [
            'view_sale_hpp',
        ];

        // Warehouse confirmation permissions are selectable in role management,
        // but are not part of the generic Admin sync list.
        $warehouseOperationPermissions = [
            'confirm_purchase_deliveries',
            'confirm_transfers',
            'confirm_sale_deliveries',
        ];

        $masterDataPermissions = [
            'manage_defect_types',
        ];

        // CRM permissions appear in the role management UI. Create them here too,
        // because the User module seeder is commonly run without the CRM seeder.
        $crmPermissions = [
            'access_crm',
            'view_all_branches',
            'show_crm_reports',
            'manage_crm_permissions',
            'create_crm_leads',
            'show_crm_leads',
            'edit_crm_leads',
            'delete_crm_leads',
            'comment_crm_leads',
            'convert_crm_leads',
            'create_crm_service_orders',
            'show_crm_service_orders',
            'edit_crm_service_orders',
            'delete_crm_service_orders',
            'assign_crm_service_orders',
            'upload_crm_photos',
            'delete_crm_photos',
            'show_crm_warranties',
            'upsert_crm_warranties',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($internalPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($warehouseOperationPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($masterDataPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach ($crmPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Pastikan role Admin ada
        $role = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        // Beri semua permission standar ke Admin (pola kamu)
        $role->syncPermissions($permissions);

        // Tetap revoke ini sesuai pola kamu
        $role->revokePermissionTo('access_user_management');

        // ✅ Pastikan role Administrator ada, dan hanya role ini yang boleh lihat HPP/Profit
        $administratorRole = Role::firstOrCreate([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);

        // Jangan pakai syncPermissions di sini biar tidak overwrite permission Administrator yang mungkin sudah ada.
        foreach ($internalPermissions as $permission) {
            if (!$administratorRole->hasPermissionTo($permission)) {
                $administratorRole->givePermissionTo($permission);
            }
        }

        // Clear cache lagi biar aman
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
