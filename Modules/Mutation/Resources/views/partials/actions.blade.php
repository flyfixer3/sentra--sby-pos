{{-- @can('edit_mutations')
    <a href="{{ route('mutations.edit', $data->id) }}" class="btn btn-info btn-sm">
        <i class="bi bi-pencil"></i>
    </a>
@endcan --}}
@can('show_mutations')
    <a href="{{ route('mutations.show', $data->id) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-eye"></i>
    </a>
@endcan
{{-- @can('delete_mutations')
    <button id="delete" class="btn btn-danger btn-sm" type="button" data-confirm-target-form="destroy{{ $data->id }}" data-confirm-title="Confirm Delete" data-confirm-message="Are you sure? It will delete the data permanently!" data-confirm-button="Delete" data-confirm-variant="danger">
        <i class="bi bi-trash"></i>
        <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('mutations.destroy', $data->id) }}" method="POST">
            @csrf
            @method('delete')
        </form>
    </button>
@endcan --}}
