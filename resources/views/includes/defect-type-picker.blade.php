@php
    $selected = \App\Support\DefectTypeSupport::normalizeList($selected ?? []);
    $canManagePickerDefectTypes = $canManagePickerDefectTypes
        ?? (auth()->check() && auth()->user()->can('manage_defect_types'));
@endphp

<div class="defect-type-picker" data-selected='@json($selected)'>
    <input type="hidden"
           class="defect-types-json-input"
           name="{{ $namePrefix }}[defect_types_json]"
           value='@json($selected)'>

    <div class="defect-type-summary">
        @if(!empty($selected))
            @foreach($selected as $value)
                <span class="defect-type-chip">{{ $value }}</span>
            @endforeach
        @else
            <span class="defect-type-summary-empty">No defect type selected.</span>
        @endif
    </div>

    <div class="defect-type-checklist"></div>

    @if($canManagePickerDefectTypes)
        <div class="defect-type-add">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control defect-type-add-input" placeholder="Add new defect type">
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-primary defect-type-add-btn">Add</button>
                </div>
            </div>
            <div class="defect-type-add-note">You can add a new master defect type here.</div>
        </div>
    @endif
</div>
