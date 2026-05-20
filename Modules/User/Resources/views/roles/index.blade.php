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

    <div class="modal fade" id="rolePermissionsModal" tabindex="-1" role="dialog" aria-labelledby="rolePermissionsModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rolePermissionsModalTitle">Permissions</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="rolePermissionsModalBody" class="d-flex flex-wrap"></div>
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

            var body = document.getElementById('rolePermissionsModalBody');
            body.innerHTML = '';

            permissions.forEach(function (permission) {
                var badge = document.createElement('span');
                badge.className = 'badge badge-primary mr-1 mb-2';
                badge.title = permission;
                badge.textContent = permission
                    .replace(/[_-]+/g, ' ')
                    .replace(/\b\w/g, function (letter) {
                        return letter.toUpperCase();
                    });
                body.appendChild(badge);
            });
        });
    </script>
@endpush
