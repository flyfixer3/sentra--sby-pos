<?php

namespace Modules\User\Http\Controllers;

use Modules\User\DataTables\RolesDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(RolesDataTable $dataTable) {
        abort_if(Gate::denies('access_user_management'), 403);

        return $dataTable->render('user::roles.index');
    }


    public function create() {
        abort_if(Gate::denies('access_user_management'), 403);

        return view('user::roles.create', [
            'additionalPermissionGroups' => $this->additionalPermissionGroups(),
        ]);
    }


    public function store(Request $request) {
        abort_if(Gate::denies('access_user_management'), 403);

        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);

        $role = Role::create([
            'name' => $request->name
        ]);

        $role->givePermissionTo($request->permissions);

        toast('Role Created With Selected Permissions!', 'success');

        return redirect()->route('roles.index');
    }


    public function edit(Role $role) {
        abort_if(Gate::denies('access_user_management'), 403);

        return view('user::roles.edit', [
            'role' => $role,
            'additionalPermissionGroups' => $this->additionalPermissionGroups(),
        ]);
    }


    public function update(Request $request, Role $role) {
        abort_if(Gate::denies('access_user_management'), 403);

        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);

        $role->update([
            'name' => $request->name
        ]);

        $role->syncPermissions($request->permissions);

        toast('Role Updated With Selected Permissions!', 'success');

        return redirect()->route('roles.index');
    }


    public function destroy(Role $role) {
        abort_if(Gate::denies('access_user_management'), 403);

        $role->delete();

        toast('Role Deleted!', 'success');

        return redirect()->route('roles.index');
    }

    private function additionalPermissionGroups(): array
    {
        $existingPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $managedInPrimaryCards = [
            'edit_own_profile',
            'access_user_management',
            'show_total_stats',
            'show_month_overview',
            'show_weekly_sales_purchases',
            'show_monthly_cashflow',
            'show_notifications',
            'access_products',
            'create_products',
            'show_products',
            'edit_products',
            'delete_products',
            'access_product_categories',
            'print_barcodes',
            'manage_defect_types',
            'access_adjustments',
            'create_adjustments',
            'show_adjustments',
            'edit_adjustments',
            'approve_adjustments',
            'delete_adjustments',
            'access_quotations',
            'create_quotations',
            'show_quotations',
            'edit_quotations',
            'delete_quotations',
            'send_quotation_mails',
            'create_quotation_sales',
            'access_expenses',
            'create_expenses',
            'edit_expenses',
            'delete_expenses',
            'access_expense_categories',
            'access_customers',
            'create_customers',
            'show_customers',
            'edit_customers',
            'update_customers',
            'delete_customers',
            'access_suppliers',
            'create_suppliers',
            'show_suppliers',
            'edit_suppliers',
            'delete_suppliers',
            'access_sales',
            'create_sales',
            'show_sales',
            'edit_sales',
            'delete_sales',
            'create_pos_sales',
            'access_sale_payments',
            'access_sale_returns',
            'create_sale_returns',
            'show_sale_returns',
            'edit_sale_returns',
            'delete_sale_returns',
            'access_sale_return_payments',
            'access_purchases',
            'create_purchases',
            'show_purchases',
            'edit_purchases',
            'delete_purchases',
            'access_purchase_payments',
            'access_purchase_returns',
            'create_purchase_returns',
            'show_purchase_returns',
            'edit_purchase_returns',
            'delete_purchase_returns',
            'delete_purchase_return',
            'access_purchase_return_payments',
            'access_currencies',
            'create_currencies',
            'edit_currencies',
            'delete_currencies',
            'access_reports',
            'access_settings',
            'confirm_purchase_deliveries',
            'confirm_transfers',
            'confirm_sale_deliveries',
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

        $groups = [
            'Product Support' => [
                'access_product_accessories' => 'Accessories',
                'access_warehouses' => 'Warehouses',
                'import_products' => 'Import Products',
            ],
            'Branches' => [
                'access_branches' => 'Access',
                'show_branches' => 'View',
                'create_branches' => 'Create',
                'edit_branches' => 'Edit',
                'delete_branches' => 'Delete',
                'create_branch' => 'Create (Legacy)',
                'edit_branch' => 'Edit (Legacy)',
                'delete_branch' => 'Delete (Legacy)',
                'switch_branch' => 'Switch Branch',
                'view_all_branches' => 'View All Branches',
            ],
            'Inventory Controls' => [
                'access_inventories' => 'Inventory Access',
                'access_invetories' => 'Inventory Access (Legacy)',
                'access_mutations' => 'Mutations Access',
                'create_mutations' => 'Create Mutations',
                'show_mutations' => 'View Mutations',
                'edit_mutations' => 'Edit Mutations',
                'delete_mutations' => 'Delete Mutations',
                'delete_inventory' => 'Delete Inventory',
                'access_racks' => 'Racks Access',
                'create_racks' => 'Create Racks',
                'edit_racks' => 'Edit Racks',
                'delete_racks' => 'Delete Racks',
                'import_racks' => 'Import Racks',
                'access_rack_movements' => 'Rack Movements Access',
                'create_rack_movements' => 'Create Rack Movements',
                'show_rack_movements' => 'View Rack Movements',
                'import_opening_stock' => 'Import Opening Stock',
            ],
            'Transfers' => [
                'access_transfers' => 'Access',
                'create_transfers' => 'Create',
                'show_transfers' => 'View',
                'delete_transfers' => 'Delete',
                'print_transfers' => 'Print',
                'cancel_transfers' => 'Cancel',
            ],
            'Purchase Orders & Deliveries' => [
                'access_purchase_orders' => 'PO Access',
                'access_purchase-orders' => 'PO Access (Legacy)',
                'create_purchase_orders' => 'Create PO',
                'create_purchase-orders' => 'Create PO (Legacy)',
                'show_purchase_orders' => 'View PO',
                'edit_purchase_orders' => 'Edit PO',
                'delete_purchase_orders' => 'Delete PO',
                'create_purchase_order_purchases' => 'Convert PO to Purchase',
                'send_purchase_order_mails' => 'Send PO Email',
                'access_purchase_deliveries' => 'Purchase Deliveries Access',
                'create_purchase_deliveries' => 'Create Purchase Deliveries',
                'show_purchase_deliveries' => 'View Purchase Deliveries',
                'edit_purchase_deliveries' => 'Edit Purchase Deliveries',
                'delete_purchase_deliveries' => 'Delete Purchase Deliveries',
                'create_purchase' => 'Create Purchase (Legacy)',
            ],
            'Sale Orders & Deliveries' => [
                'access_sale_orders' => 'Sale Orders Access',
                'create_sale_orders' => 'Create Sale Orders',
                'show_sale_orders' => 'View Sale Orders',
                'edit_sale_orders' => 'Edit Sale Orders',
                'delete_sale_orders' => 'Delete Sale Orders',
                'create_sale_invoices' => 'Create Sale Invoices',
                'access_sale_deliveries' => 'Sale Deliveries Access',
                'create_sale_deliveries' => 'Create Sale Deliveries',
                'show_sale_deliveries' => 'View Sale Deliveries',
                'edit_sale_deliveries' => 'Edit Sale Deliveries',
                'delete_sale_deliveries' => 'Delete Sale Deliveries',
            ],
            'Internal / Finance' => [
                'view_sale_hpp' => 'View HPP / Gross Profit',
            ],
        ];

        return collect($groups)
            ->map(function (array $permissions) use ($existingPermissions, $managedInPrimaryCards) {
                return collect($permissions)
                    ->filter(function ($label, $permission) use ($existingPermissions, $managedInPrimaryCards) {
                        return in_array($permission, $existingPermissions, true)
                            && !in_array($permission, $managedInPrimaryCards, true);
                    })
                    ->all();
            })
            ->filter(fn (array $permissions) => !empty($permissions))
            ->all();
    }
}
