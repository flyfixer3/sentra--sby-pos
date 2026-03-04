<div class="btn-group dropleft">
    <button type="button" class="btn btn-ghost-primary dropdown rounded" data-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-three-dots-vertical"></i>
    </button>

    <div class="dropdown-menu">

        {{-- Kalau sudah deleted: tampilkan Restore + Force Delete --}}
        @if(!empty($data->deleted_at))

            @can('delete_sales')
                <button class="dropdown-item" onclick="
                    event.preventDefault();
                    if (confirm('Restore this Sale?')) {
                        document.getElementById('restore{{ $data->id }}').submit()
                    }">
                    <i class="bi bi-arrow-counterclockwise mr-2 text-success" style="line-height:1;"></i> Restore
                </button>
                <form id="restore{{ $data->id }}" class="d-none" action="{{ route('sales.restore', $data->id) }}" method="POST">
                    @csrf
                    @method('patch')
                </form>
            @endcan

            @can('delete_sales')
                <button class="dropdown-item" onclick="
                    event.preventDefault();
                    if (confirm('Force delete? This will delete permanently and cannot be undone!')) {
                        document.getElementById('forceDestroy{{ $data->id }}').submit()
                    }">
                    <i class="bi bi-trash-fill mr-2 text-danger" style="line-height:1;"></i> Force Delete
                </button>
                <form id="forceDestroy{{ $data->id }}" class="d-none" action="{{ route('sales.force-destroy', $data->id) }}" method="POST">
                    @csrf
                    @method('delete')
                </form>
            @endcan

        @else
            {{-- Normal (not deleted) --}}

            @can('show_sales')
                <a href="{{ route('sales.show', $data->id) }}" class="dropdown-item">
                    <i class="bi bi-eye mr-2 text-info" style="line-height: 1;"></i> Details
                </a>
            @endcan

            @can('edit_sales')
                <a href="{{ route('sales.edit', $data->id) }}" class="dropdown-item">
                    <i class="bi bi-pencil mr-2 text-primary" style="line-height: 1;"></i> Edit
                </a>
            @endcan

            @can('delete_sales')
                <button class="dropdown-item" onclick="
                    event.preventDefault();
                    if (confirm('Soft delete this Sale? You can restore it later.')) {
                        document.getElementById('destroy{{ $data->id }}').submit()
                    }">
                    <i class="bi bi-trash mr-2 text-danger" style="line-height: 1;"></i> Delete
                </button>
                <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('sales.destroy', $data->id) }}" method="POST">
                    @csrf
                    @method('delete')
                </form>
            @endcan

        @endif

    </div>
</div>