@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@push('page_css')
    <style>
        .role-permission-badges {
            align-items: center;
            justify-content: center;
            row-gap: .25rem;
        }

        .role-permission-badge,
        .role-permission-more-btn {
            display: inline-flex;
            align-items: center;
            min-height: 1.55rem;
            margin: .125rem;
            padding: .28rem .45rem;
            font-size: 0.78rem;
            line-height: 1.15;
            white-space: normal;
        }

        .role-permission-more-btn {
            cursor: pointer;
            border: 1px solid #39f;
            background: #fff;
            color: #321fdb;
        }

        .role-permission-more-btn:hover,
        .role-permission-more-btn:focus {
            background: #ebf5ff;
            color: #321fdb;
            text-decoration: none;
        }

        .role-permission-modal .modal-body {
            max-height: calc(100vh - 220px);
            overflow-y: auto;
        }

        .role-permission-search {
            max-width: 360px;
        }

        .role-permission-group {
            border: 1px solid #d8dbe0;
            border-radius: .25rem;
            background: #fff;
        }

        .role-permission-group-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .55rem .75rem;
            border-bottom: 1px solid #ebedef;
            background: #f8f9fa;
            font-weight: 600;
        }

        .role-permission-group-body {
            padding: .65rem .75rem .5rem;
        }

        .role-permission-modal-badge {
            display: inline-flex;
            align-items: center;
            margin: .125rem;
            padding: .32rem .48rem;
            line-height: 1.15;
            white-space: normal;
        }
    </style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Roles</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <!-- Button trigger modal -->
                        <a href="{{ route('roles.create') }}" class="btn btn-primary">
                            Add Role <i class="bi bi-plus"></i>
                        </a>

                        <hr>

                        <div class="table-responsive">
                            {!! $dataTable->table() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade role-permission-modal" id="rolePermissionsModal" tabindex="-1" role="dialog" aria-labelledby="rolePermissionsModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rolePermissionsModalTitle">Permissions</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-md-flex align-items-center justify-content-between mb-3">
                        <div id="rolePermissionsModalSummary" class="text-muted small mb-2 mb-md-0">Total permissions: 0</div>
                        <input type="search"
                               id="rolePermissionsSearch"
                               class="form-control form-control-sm role-permission-search"
                               placeholder="Search permissions..."
                               autocomplete="off">
                    </div>
                    <div id="rolePermissionsModalBody" class="row"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
    <script>
        var currentRolePermissions = [];

        var permissionActionWords = [
            'access', 'assign', 'approve', 'cancel', 'comment', 'confirm', 'convert', 'create',
            'delete', 'edit', 'import', 'manage', 'print', 'send', 'show', 'switch',
            'update', 'upload', 'upsert', 'view'
        ];

        var permissionExactGroups = {
            edit_own_profile: 'Profile',
            show_total_stats: 'Dashboard',
            show_month_overview: 'Dashboard',
            show_weekly_sales_purchases: 'Dashboard',
            show_monthly_cashflow: 'Dashboard',
            show_notifications: 'Notifications',
            access_settings: 'Settings',
            access_reports: 'Reports',
            view_sale_hpp: 'Reports',
            view_all_branches: 'Branches'
        };

        var permissionGroupPatterns = [
            { label: 'Purchase Deliveries', phrases: ['purchase deliveries', 'purchase delivery'] },
            { label: 'Purchase Orders', phrases: ['purchase orders', 'purchase order', 'purchase order purchases'] },
            { label: 'Purchase Returns', phrases: ['purchase returns', 'purchase return'] },
            { label: 'Purchase Payments', phrases: ['purchase payments', 'purchase payment'] },
            { label: 'Sale Deliveries', phrases: ['sale deliveries', 'sale delivery'] },
            { label: 'Sale Orders', phrases: ['sale orders', 'sale order'] },
            { label: 'Sale Returns', phrases: ['sale returns', 'sale return'] },
            { label: 'Sale Payments', phrases: ['sale payments', 'sale payment'] },
            { label: 'Sale Invoices', phrases: ['sale invoices', 'sale invoice', 'invoices', 'invoice'] },
            { label: 'Adjustments', phrases: ['adjustments', 'adjustment'] },
            { label: 'Branches', phrases: ['branches', 'branch'] },
            { label: 'Currencies', phrases: ['currencies', 'currency'] },
            { label: 'Customers', phrases: ['customers', 'customer'] },
            { label: 'Expenses', phrases: ['expense categories', 'expenses', 'expense'] },
            { label: 'Inventories', phrases: ['inventories', 'invetories', 'inventory'] },
            { label: 'Mutations', phrases: ['mutations', 'mutation'] },
            { label: 'Products', phrases: ['product categories', 'product accessories', 'products', 'product'] },
            { label: 'Purchases', phrases: ['purchases', 'purchase'] },
            { label: 'Quotations', phrases: ['quotations', 'quotation'] },
            { label: 'Racks', phrases: ['rack movements', 'racks', 'rack'] },
            { label: 'Reports', phrases: ['reports', 'report', 'hpp', 'profit'] },
            { label: 'Sales', phrases: ['sales', 'sale'] },
            { label: 'Suppliers', phrases: ['suppliers', 'supplier'] },
            { label: 'Transfers', phrases: ['transfers', 'transfer'] },
            { label: 'Warehouses', phrases: ['warehouses', 'warehouse'] },
            { label: 'CRM', phrases: ['crm', 'leads', 'lead', 'service orders', 'service order', 'warranties', 'warranty', 'photos', 'photo'] },
            { label: 'User Management', phrases: ['user management', 'users', 'user'] }
        ];

        function permissionWords(permission) {
            return String(permission || '')
                .replace(/([a-z])([A-Z])/g, '$1 $2')
                .replace(/[_-]+/g, ' ')
                .trim()
                .split(/\s+/)
                .filter(Boolean);
        }

        function permissionLabel(permission) {
            return permissionWords(permission)
                .map(function (word) {
                    return word.charAt(0).toUpperCase() + word.slice(1);
                })
                .join(' ');
        }

        function permissionGroup(permission) {
            var raw = String(permission || '').toLowerCase();
            if (permissionExactGroups[raw]) {
                return permissionExactGroups[raw];
            }

            var words = permissionWords(permission).map(function (word) {
                return word.toLowerCase();
            });

            if (words.length && permissionActionWords.indexOf(words[0]) !== -1) {
                words.shift();
            }

            var phrase = words.join(' ');
            for (var i = 0; i < permissionGroupPatterns.length; i++) {
                var pattern = permissionGroupPatterns[i];
                for (var j = 0; j < pattern.phrases.length; j++) {
                    if (phrase.indexOf(pattern.phrases[j]) !== -1 || raw.indexOf(pattern.phrases[j].replace(/\s+/g, '_')) !== -1) {
                        return pattern.label;
                    }
                }
            }

            return phrase ? permissionLabel(phrase) : 'Others';
        }

        function groupedPermissions(permissions, searchTerm) {
            var normalizedSearch = String(searchTerm || '').toLowerCase().trim();
            var groups = {};

            permissions.forEach(function (permission) {
                var label = permissionLabel(permission);
                var searchable = (permission + ' ' + label).toLowerCase();
                if (normalizedSearch && searchable.indexOf(normalizedSearch) === -1) {
                    return;
                }

                var group = permissionGroup(permission);
                if (!groups[group]) {
                    groups[group] = [];
                }

                groups[group].push({
                    name: permission,
                    label: label
                });
            });

            return groups;
        }

        function renderPermissionGroups(searchTerm) {
            var body = document.getElementById('rolePermissionsModalBody');
            body.innerHTML = '';

            var groups = groupedPermissions(currentRolePermissions, searchTerm);
            var groupNames = Object.keys(groups).sort(function (a, b) {
                if (a === 'Others') return 1;
                if (b === 'Others') return -1;
                return a.localeCompare(b);
            });

            if (!groupNames.length) {
                var empty = document.createElement('div');
                empty.className = 'col-12 text-muted text-center py-4';
                empty.textContent = 'No permissions match your search.';
                body.appendChild(empty);
                return;
            }

            groupNames.forEach(function (groupName) {
                var permissions = groups[groupName];
                var column = document.createElement('div');
                column.className = 'col-lg-6 mb-3';

                var group = document.createElement('div');
                group.className = 'role-permission-group h-100';

                var header = document.createElement('div');
                header.className = 'role-permission-group-header';

                var title = document.createElement('span');
                title.textContent = groupName;

                var count = document.createElement('span');
                count.className = 'badge badge-light border text-muted';
                count.textContent = permissions.length;

                var groupBody = document.createElement('div');
                groupBody.className = 'role-permission-group-body';

                permissions.forEach(function (permission) {
                    var badge = document.createElement('span');
                    badge.className = 'badge badge-primary role-permission-modal-badge';
                    badge.title = permission.name;
                    badge.textContent = permission.label;
                    groupBody.appendChild(badge);
                });

                header.appendChild(title);
                header.appendChild(count);
                group.appendChild(header);
                group.appendChild(groupBody);
                column.appendChild(group);
                body.appendChild(column);
            });
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.role-permission-more-btn');
            if (!trigger) {
                return;
            }

            var roleName = trigger.getAttribute('data-role-name') || 'Role';
            var permissions = [];

            try {
                permissions = JSON.parse(trigger.getAttribute('data-permissions') || '[]');
            } catch (error) {
                permissions = [];
            }

            document.getElementById('rolePermissionsModalTitle').textContent = 'Permissions for ' + roleName;
            document.getElementById('rolePermissionsModalSummary').textContent = 'Total permissions: ' + permissions.length;

            currentRolePermissions = permissions;
            document.getElementById('rolePermissionsSearch').value = '';
            renderPermissionGroups('');
        });

        document.getElementById('rolePermissionsSearch').addEventListener('input', function (event) {
            renderPermissionGroups(event.target.value);
        });
    </script>
@endpush
