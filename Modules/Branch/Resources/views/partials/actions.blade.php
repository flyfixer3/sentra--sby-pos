@can('edit_branches')
    <a href="{{ route('branches.edit', $data->id) }}" class="btn btn-info btn-sm">
        <i class="bi bi-pencil"></i>
    </a>
@endcan
@can('delete_branches')
    <button id="delete" class="btn btn-danger btn-sm" type="button" data-confirm-target-form="destroy{{ $data->id }}" data-confirm-title="Confirm Delete" data-confirm-message="Are you sure? It will delete the data permanently!" data-confirm-button="Delete" data-confirm-variant="danger">
        <i class="bi bi-trash"></i>
        <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('branches.destroy', $data->id) }}" method="POST">
            @csrf
            @method('delete')
        </form>
    </button>
@endcan
