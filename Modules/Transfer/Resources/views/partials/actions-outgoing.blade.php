@php
    $canDelete = in_array(($row->status ?? 'pending'), ['draft', 'pending']); // sesuaikan SOP kamu
@endphp

<div class="btn-group btn-group-sm" role="group">
    <a href="{{ route('transfers.show', $row->id) }}" class="btn btn-info">
        <i class="bi bi-eye"></i> Detail
    </a>

    @if($canDelete)
        <form action="{{ route('transfers.destroy', $row->id) }}" method="POST" onsubmit="return confirm('Hapus transfer ini?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger" title="Delete">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    @endif
</div>
