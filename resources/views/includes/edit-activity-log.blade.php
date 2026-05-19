@php
    $editLogTitle = $title ?? 'Edit Activity Log';
    $editLogEmptyMessage = $emptyMessage ?? 'No edit log yet.';
    $editLogLimit = $limit ?? 20;
    $editLogModel = $model ?? $record ?? null;
    $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'remember_token',
        'token',
        'otp',
        'secret',
        'api_key',
    ];

    $isSensitiveField = function ($field) use ($sensitiveFields) {
        $field = strtolower((string) $field);

        foreach ($sensitiveFields as $sensitiveField) {
            if (\Illuminate\Support\Str::contains($field, $sensitiveField)) {
                return true;
            }
        }

        return false;
    };

    $isEditActivity = function ($activity) {
        $event = strtolower((string) ($activity->event ?? ''));
        $description = strtolower((string) ($activity->description ?? ''));

        return $event === 'updated'
            || \Illuminate\Support\Str::contains($description, ['updated', 'edit', 'edited', 'correction', 'corrected']);
    };

    $formatFieldLabel = function ($field) {
        $label = \Illuminate\Support\Str::of((string) $field)
            ->replace('_id', '')
            ->replace('_', ' ')
            ->title();

        return (string) $label;
    };

    $formatLogValue = function ($value) {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded ?: '-';
        }

        return (string) $value;
    };

    $extractChanges = function ($activity) use ($isSensitiveField, $formatFieldLabel, $formatLogValue) {
        $properties = $activity->properties;

        if ($properties instanceof \Illuminate\Support\Collection) {
            $properties = $properties->toArray();
        }

        $properties = is_array($properties) ? $properties : [];
        $oldValues = $properties['old'] ?? [];
        $newValues = $properties['attributes'] ?? [];

        if ($oldValues instanceof \Illuminate\Support\Collection) {
            $oldValues = $oldValues->toArray();
        }

        if ($newValues instanceof \Illuminate\Support\Collection) {
            $newValues = $newValues->toArray();
        }

        $oldValues = is_array($oldValues) ? $oldValues : [];
        $newValues = is_array($newValues) ? $newValues : [];
        $fields = collect(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))));
        $changes = $fields
            ->reject(fn ($field) => $isSensitiveField($field))
            ->map(function ($field) use ($oldValues, $newValues, $formatFieldLabel, $formatLogValue) {
                $oldValue = array_key_exists($field, $oldValues) ? $oldValues[$field] : null;
                $newValue = array_key_exists($field, $newValues) ? $newValues[$field] : null;

                if ($oldValue === $newValue) {
                    return null;
                }

                return [
                    'field' => $formatFieldLabel($field),
                    'old' => $formatLogValue($oldValue),
                    'new' => $formatLogValue($newValue),
                ];
            })
            ->filter()
            ->values();

        if ($changes->isNotEmpty()) {
            return $changes;
        }

        $changedProducts = $properties['changed_products'] ?? [];

        if (is_array($changedProducts)) {
            foreach ($changedProducts as $productId => $change) {
                if (!is_array($change)) {
                    continue;
                }

                if (array_key_exists('old_unit_cost', $change) || array_key_exists('new_unit_cost', $change)) {
                    $changes->push([
                        'field' => 'Product #' . $productId . ' Unit Cost',
                        'old' => $formatLogValue($change['old_unit_cost'] ?? null),
                        'new' => $formatLogValue($change['new_unit_cost'] ?? null),
                    ]);
                }

                if (array_key_exists('old_qty', $change) || array_key_exists('new_qty', $change)) {
                    $changes->push([
                        'field' => 'Product #' . $productId . ' Quantity',
                        'old' => $formatLogValue($change['old_qty'] ?? null),
                        'new' => $formatLogValue($change['new_qty'] ?? null),
                    ]);
                }
            }
        }

        return $changes->values();
    };

    if (isset($activities) && $activities instanceof \Illuminate\Support\Collection) {
        $editActivities = $activities
            ->filter($isEditActivity)
            ->sortByDesc('created_at')
            ->take($editLogLimit)
            ->values();
    } elseif ($editLogModel && method_exists($editLogModel, 'getKey') && $editLogModel->getKey()) {
        $editActivities = \Spatie\Activitylog\Models\Activity::query()
            ->with('causer')
            ->where('subject_type', get_class($editLogModel))
            ->where('subject_id', $editLogModel->getKey())
            ->where(function ($query) {
                $query->where('event', 'updated')
                    ->orWhere('description', 'like', '%updated%')
                    ->orWhere('description', 'like', '%edit%')
                    ->orWhere('description', 'like', '%correction%')
                    ->orWhere('description', 'like', '%corrected%');
            })
            ->orderByDesc('created_at')
            ->limit($editLogLimit)
            ->get();
    } else {
        $editActivities = collect();
    }
@endphp

<div class="card mt-4">
    <div class="card-header" style="font-weight:800;">
        {{ $editLogTitle }}
    </div>
    <div class="card-body">
        @forelse($editActivities as $activity)
            @php
                $changes = $extractChanges($activity);
                $causerName = 'System';

                if ($activity->causer) {
                    $causerName = $activity->causer->name
                        ?? $activity->causer->email
                        ?? ('User#' . $activity->causer_id);
                }
            @endphp

            <div class="{{ $loop->last ? '' : 'border-bottom pb-3 mb-3' }}">
                <div class="d-flex flex-wrap justify-content-between align-items-start mb-2" style="gap:8px;">
                    <div>
                        <div style="font-weight:800;">
                            <span class="badge bg-secondary">Updated</span>
                            <span class="ml-1">{{ $activity->description ?: 'Updated record' }}</span>
                        </div>
                        <div class="text-muted small mt-1">
                            By <strong>{{ $causerName }}</strong>
                        </div>
                    </div>
                    <div class="text-muted small">
                        {{ optional($activity->created_at)->format('d M Y H:i:s') }}
                    </div>
                </div>

                @if($changes->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:24%;">Field</th>
                                    <th>Before</th>
                                    <th>After</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($changes as $change)
                                    <tr>
                                        <td style="font-weight:700;">{{ $change['field'] }}</td>
                                        <td style="word-break:break-word;">{{ $change['old'] }}</td>
                                        <td style="word-break:break-word;">{{ $change['new'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted small">No field-level changes captured for this edit.</div>
                @endif
            </div>
        @empty
            <div class="text-muted">{{ $editLogEmptyMessage }}</div>
        @endforelse
    </div>
</div>
