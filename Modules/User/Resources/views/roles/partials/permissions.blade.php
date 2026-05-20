@php
    $permissions = $data->permissions
        ->pluck('name')
        ->filter()
        ->sort()
        ->values();

    $visibleLimit = 10;
    $visiblePermissions = $permissions->take($visibleLimit);
    $hiddenCount = max($permissions->count() - $visibleLimit, 0);

    $formatPermissionLabel = function ($permission) {
        return ucwords(str_replace(['_', '-'], ' ', (string) $permission));
    };
@endphp

@if($permissions->isEmpty())
    <span class="badge badge-light border text-muted">No permissions</span>
@else
    <div class="role-permission-badges d-flex flex-wrap">
        @foreach($visiblePermissions as $permission)
            <span class="badge badge-primary role-permission-badge" title="{{ $permission }}">
                {{ $formatPermissionLabel($permission) }}
            </span>
        @endforeach

        @if($hiddenCount > 0)
            <button type="button"
                    class="badge role-permission-more-btn"
                    data-toggle="modal"
                    data-target="#rolePermissionsModal"
                    data-role-name="{{ $data->name }}"
                    data-permissions='@json($permissions->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                    title="View all permissions for {{ $data->name }}">
                +{{ $hiddenCount }} more
            </button>
        @endif
    </div>
@endif
