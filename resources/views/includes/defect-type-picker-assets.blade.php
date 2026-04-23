@php
    $defectTypeOptions = $defectTypeOptions
        ?? \Modules\Product\Entities\DefectType::query()->orderBy('name')->pluck('name')->values()->all();

    $canManageDefectTypes = $canManageDefectTypes
        ?? (auth()->check() && auth()->user()->hasAnyRole(['Administrator', 'Super Admin']));

    $canDeleteDefectTypes = $canDeleteDefectTypes
        ?? (auth()->check() && auth()->user()->hasAnyRole(['Administrator', 'Super Admin']));
@endphp

@once
    @push('page_css')
    <style>
        .defect-type-picker {
            border: 1px solid rgba(0,0,0,.08);
            border-radius: 10px;
            padding: 10px;
            background: #fff;
        }
        .defect-type-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 8px;
        }
        .defect-type-summary-empty {
            font-size: 12px;
            color: #6c757d;
        }
        .defect-type-chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(13,110,253,.08);
            color: #0d6efd;
            border: 1px solid rgba(13,110,253,.18);
        }
        .defect-type-checklist {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 6px 12px;
        }
        .defect-type-check-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 8px;
            padding: 4px 6px;
        }
        .defect-type-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #212529;
            margin: 0;
            flex: 1;
        }
        .defect-type-check input {
            margin: 0;
        }
        .defect-type-remove-btn {
            border: 0;
            background: transparent;
            color: #adb5bd;
            font-size: 14px;
            line-height: 1;
            padding: 2px 4px;
            border-radius: 4px;
        }
        .defect-type-remove-btn:hover {
            color: #dc3545;
            background: rgba(220,53,69,.08);
        }
        .defect-type-add {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed rgba(0,0,0,.08);
        }
        .defect-type-add-note {
            font-size: 11px;
            color: #6c757d;
            margin-top: 6px;
        }
    </style>
    @endpush

    @push('page_scripts')
    <script>
        (function () {
            if (window.__defectTypePickerReady === true) return;
            window.__defectTypePickerReady = true;

            window.DEFECT_TYPE_OPTIONS = @json($defectTypeOptions);
            window.CAN_MANAGE_DEFECT_TYPES = @json((bool) $canManageDefectTypes);
            window.CAN_DELETE_DEFECT_TYPES = @json((bool) $canDeleteDefectTypes);
            window.DEFECT_TYPE_CREATE_URL = @json(route('defect-types.store'));
            window.DEFECT_TYPE_DELETE_URL = @json(route('defect-types.destroy'));
            window.DEFECT_TYPE_CSRF = @json(csrf_token());

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function normalizeDefectTypes(raw) {
                let values = raw;

                if (typeof values === 'string') {
                    const text = values.trim();
                    if (text === '') return [];

                    try {
                        const decoded = JSON.parse(text);
                        if (Array.isArray(decoded)) {
                            values = decoded;
                        } else {
                            values = text.split(',');
                        }
                    } catch (e) {
                        values = text.split(',');
                    }
                }

                if (!Array.isArray(values)) return [];

                const map = new Map();
                values.forEach((entry) => {
                    const label = String(entry || '').trim();
                    if (label === '') return;
                    const key = label.toLowerCase();
                    if (!map.has(key)) map.set(key, label);
                });

                return Array.from(map.values());
            }

            function readSelectedFromPicker(picker) {
                if (!picker) return [];

                const checked = Array.from(picker.querySelectorAll('.defect-type-option:checked')).map((input) => input.value);
                if (checked.length > 0) {
                    return normalizeDefectTypes(checked);
                }

                return normalizeDefectTypes(picker.dataset.selected || '[]');
            }

            function updatePickerSummary(picker, selected) {
                const summary = picker.querySelector('.defect-type-summary');
                if (!summary) return;

                if (!selected.length) {
                    summary.innerHTML = '<span class="defect-type-summary-empty">No defect type selected.</span>';
                    return;
                }

                summary.innerHTML = selected.map((value) => (
                    '<span class="defect-type-chip">' + escapeHtml(value) + '</span>'
                )).join('');
            }

            function updatePickerHiddenInputs(picker, selected) {
                const jsonInput = picker.querySelector('.defect-types-json-input');
                const legacyInput = picker.querySelector('.defect-type-legacy-input');

                if (jsonInput) jsonInput.value = JSON.stringify(selected);
                if (legacyInput) legacyInput.value = selected.join(', ');
                picker.dataset.selected = JSON.stringify(selected);

                if (legacyInput) {
                    legacyInput.dispatchEvent(new Event('input', { bubbles: true }));
                    legacyInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (jsonInput) {
                    jsonInput.dispatchEvent(new Event('input', { bubbles: true }));
                    jsonInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            function renderPickerChecklist(picker) {
                const selected = readSelectedFromPicker(picker);
                const list = picker.querySelector('.defect-type-checklist');
                if (!list) return;

                const options = normalizeDefectTypes(window.DEFECT_TYPE_OPTIONS || []);
                list.innerHTML = options.map((label) => {
                    const checked = selected.includes(label) ? 'checked' : '';
                    const removeHtml = window.CAN_DELETE_DEFECT_TYPES
                        ? ('<button type="button" class="defect-type-remove-btn" data-defect-type="' + escapeHtml(label) + '" title="Remove defect type">&times;</button>')
                        : '';

                    return ''
                        + '<div class="defect-type-check-row">'
                        + '  <label class="defect-type-check">'
                        + '    <input type="checkbox" class="defect-type-option" value="' + escapeHtml(label) + '" ' + checked + '>'
                        + '    <span>' + escapeHtml(label) + '</span>'
                        + '  </label>'
                        + removeHtml
                        + '</div>';
                }).join('');

                updatePickerSummary(picker, selected);
                updatePickerHiddenInputs(picker, selected);
            }

            function bindPickerEvents(picker) {
                if (!picker || picker.dataset.bound === '1') return;
                picker.dataset.bound = '1';

                picker.addEventListener('change', function (event) {
                    if (!event.target.classList.contains('defect-type-option')) return;
                    const selected = readSelectedFromPicker(picker);
                    updatePickerSummary(picker, selected);
                    updatePickerHiddenInputs(picker, selected);
                });

                picker.addEventListener('click', async function (event) {
                    const removeBtn = event.target.closest('.defect-type-remove-btn');
                    if (removeBtn) {
                        event.preventDefault();
                        if (!window.CAN_DELETE_DEFECT_TYPES) return;

                        const note = picker.querySelector('.defect-type-add-note');
                        const value = String(removeBtn.getAttribute('data-defect-type') || '').trim();
                        if (value === '') return;

                        if (!window.confirm('Remove defect type "' + value + '" from master list?')) return;

                        removeBtn.disabled = true;
                        if (note) note.textContent = 'Removing...';

                        try {
                            const response = await fetch(window.DEFECT_TYPE_DELETE_URL, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': window.DEFECT_TYPE_CSRF,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ name: value })
                            });

                            const payload = await response.json();
                            if (!response.ok || !payload || payload.success !== true) {
                                throw new Error((payload && payload.message) ? payload.message : 'Failed to remove defect type.');
                            }

                            const removedName = String((payload.data && payload.data.name) ? payload.data.name : value).trim();

                            window.DEFECT_TYPE_OPTIONS = normalizeDefectTypes(window.DEFECT_TYPE_OPTIONS || []).filter((entry) => (
                                String(entry).trim().toLowerCase() !== removedName.toLowerCase()
                            ));

                            document.querySelectorAll('.defect-type-picker').forEach((targetPicker) => {
                                const next = readSelectedFromPicker(targetPicker).filter((entry) => (
                                    String(entry).trim().toLowerCase() !== removedName.toLowerCase()
                                ));
                                targetPicker.dataset.selected = JSON.stringify(normalizeDefectTypes(next));
                                renderPickerChecklist(targetPicker);
                            });

                            if (note) note.textContent = 'Removed from master list.';
                        } catch (error) {
                            if (note) note.textContent = error.message || 'Failed to remove defect type.';
                        }

                        return;
                    }

                    const button = event.target.closest('.defect-type-add-btn');
                    if (!button) return;

                    event.preventDefault();
                    if (!window.CAN_MANAGE_DEFECT_TYPES) return;

                    const input = picker.querySelector('.defect-type-add-input');
                    const note = picker.querySelector('.defect-type-add-note');
                    const value = String(input?.value || '').trim();
                    if (value === '') {
                        if (note) note.textContent = 'Defect type name is required.';
                        return;
                    }

                    button.disabled = true;
                    if (note) note.textContent = 'Saving...';

                    try {
                        const response = await fetch(window.DEFECT_TYPE_CREATE_URL, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': window.DEFECT_TYPE_CSRF,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ name: value })
                        });

                        const payload = await response.json();
                        if (!response.ok || !payload || payload.success !== true || !payload.data || !payload.data.name) {
                            throw new Error((payload && payload.message) ? payload.message : 'Failed to create defect type.');
                        }

                        const name = String(payload.data.name || '').trim();
                        if (name !== '' && !normalizeDefectTypes(window.DEFECT_TYPE_OPTIONS).includes(name)) {
                            window.DEFECT_TYPE_OPTIONS.push(name);
                        }

                        const selected = readSelectedFromPicker(picker);
                        selected.push(name);
                        picker.dataset.selected = JSON.stringify(normalizeDefectTypes(selected));
                        renderPickerChecklist(picker);

                        const targetCheckbox = picker.querySelector('.defect-type-option[value="' + (window.CSS && CSS.escape ? CSS.escape(name) : name.replace(/"/g, '\\"')) + '"]');
                        if (targetCheckbox) targetCheckbox.checked = true;
                        updatePickerSummary(picker, readSelectedFromPicker(picker));
                        updatePickerHiddenInputs(picker, readSelectedFromPicker(picker));

                        if (input) input.value = '';
                        if (note) note.textContent = payload.data.existing ? 'Already exists and selected.' : 'Saved and selected.';
                    } catch (error) {
                        if (note) note.textContent = error.message || 'Failed to create defect type.';
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            window.renderDefectTypePickerHtml = function (namePrefix, selected) {
                const values = normalizeDefectTypes(selected);
                const options = normalizeDefectTypes(window.DEFECT_TYPE_OPTIONS || []);
                const summaryHtml = values.length
                    ? values.map((value) => '<span class="defect-type-chip">' + escapeHtml(value) + '</span>').join('')
                    : '<span class="defect-type-summary-empty">No defect type selected.</span>';

                const checklistHtml = options.map((label) => {
                    const checked = values.includes(label) ? 'checked' : '';
                    const removeHtml = window.CAN_DELETE_DEFECT_TYPES
                        ? ('<button type="button" class="defect-type-remove-btn" data-defect-type="' + escapeHtml(label) + '" title="Remove defect type">&times;</button>')
                        : '';

                    return ''
                        + '<div class="defect-type-check-row">'
                        + '  <label class="defect-type-check">'
                        + '    <input type="checkbox" class="defect-type-option" value="' + escapeHtml(label) + '" ' + checked + '>'
                        + '    <span>' + escapeHtml(label) + '</span>'
                        + '  </label>'
                        + removeHtml
                        + '</div>';
                }).join('');

                const addHtml = window.CAN_MANAGE_DEFECT_TYPES ? (
                    '<div class="defect-type-add">'
                    + '  <div class="input-group input-group-sm">'
                    + '    <input type="text" class="form-control defect-type-add-input" placeholder="Add new defect type">'
                    + '    <div class="input-group-append">'
                    + '      <button type="button" class="btn btn-outline-primary defect-type-add-btn">Add</button>'
                    + '    </div>'
                    + '  </div>'
                    + '  <div class="defect-type-add-note">Administrator can add a new master defect type here.</div>'
                    + '</div>'
                ) : '';

                return ''
                    + '<div class="defect-type-picker" data-selected="' + escapeHtml(JSON.stringify(values)) + '">'
                    + '  <input type="hidden" class="defect-type-legacy-input" name="' + escapeHtml(namePrefix) + '[defect_type]" value="' + escapeHtml(values.join(', ')) + '">'
                    + '  <input type="hidden" class="defect-types-json-input" name="' + escapeHtml(namePrefix) + '[defect_types_json]" value="' + escapeHtml(JSON.stringify(values)) + '">'
                    + '  <div class="defect-type-summary">' + summaryHtml + '</div>'
                    + '  <div class="defect-type-checklist">' + checklistHtml + '</div>'
                    + addHtml
                    + '</div>';
            };

            window.initDefectTypePickers = function (scope) {
                const root = scope || document;
                root.querySelectorAll('.defect-type-picker').forEach((picker) => {
                    renderPickerChecklist(picker);
                    bindPickerEvents(picker);
                });
            };

            window.getSelectedDefectTypes = function (picker) {
                return readSelectedFromPicker(picker);
            };

            document.addEventListener('DOMContentLoaded', function () {
                window.initDefectTypePickers(document);
            });
        })();
    </script>
    @endpush
@endonce
