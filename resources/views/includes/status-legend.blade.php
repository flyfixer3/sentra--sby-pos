@php
    $rawLegendId = $id ?? ('status-legend-' . md5(($title ?? 'Status Legend') . json_encode($items ?? [])));
    $legendId = preg_replace('/[^A-Za-z0-9_-]/', '-', $rawLegendId);
    $legendId = $legendId ?: 'status-legend-' . md5(($title ?? 'Status Legend') . json_encode($items ?? []));
    $legendId = preg_match('/^[A-Za-z]/', $legendId) ? $legendId : 'status-legend-' . $legendId;
    $legendTitle = $title ?? 'Status Legend';
    $legendItems = $items ?? [];
@endphp

@once
    <style>
        .status-legend-wrap {
            font-size: .8125rem;
        }

        .status-legend-panel .card {
            border-color: rgba(0, 0, 0, .08);
            border-radius: .5rem;
        }

        .status-legend-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }

        .status-legend-title {
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: #6c757d;
        }

        .status-legend-close {
            border: 0;
            background: transparent;
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1;
            padding: .1rem .25rem;
            cursor: pointer;
        }

        .status-legend-close:hover,
        .status-legend-close:focus {
            color: #2f353a;
            outline: none;
        }

        .status-legend-list {
            display: grid;
            gap: .5rem;
        }

        .status-legend-item {
            display: grid;
            grid-template-columns: minmax(115px, 155px) minmax(0, 1fr) minmax(0, 1.25fr);
            gap: .75rem;
            align-items: start;
            padding: .55rem .65rem;
            border: 1px solid rgba(0, 0, 0, .06);
            border-radius: .4rem;
            background: #fff;
        }

        .status-legend-label {
            display: block;
            margin-bottom: .1rem;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #8a93a2;
        }

        .status-legend-text {
            color: #3c4b64;
            line-height: 1.35;
        }

        @media (max-width: 767.98px) {
            .status-legend-item {
                grid-template-columns: 1fr;
                gap: .4rem;
            }
        }
    </style>

    <script>
        document.addEventListener('click', function (event) {
            var openButton = event.target.closest ? event.target.closest('.js-status-legend-open') : null;
            var closeButton = event.target.closest ? event.target.closest('.js-status-legend-close') : null;
            var button = openButton || closeButton;

            if (!button) return;

            var targetSelector = button.getAttribute('data-status-legend-target');
            if (!targetSelector) return;

            var panel = document.querySelector(targetSelector);
            if (!panel || !panel.classList.contains('status-legend-panel')) return;

            if (openButton) {
                var shouldOpen = panel.classList.contains('d-none');
                panel.classList.toggle('d-none', !shouldOpen);
                openButton.setAttribute('aria-expanded', String(shouldOpen));
                return;
            }

            panel.classList.add('d-none');

            var relatedOpenButton = document.querySelector('.js-status-legend-open[data-status-legend-target="' + targetSelector + '"]');
            if (relatedOpenButton) {
                relatedOpenButton.setAttribute('aria-expanded', 'false');
            }
        });
    </script>
@endonce

<div class="mb-3 status-legend-wrap">
    <button
        class="btn btn-sm btn-outline-info status-legend-toggle js-status-legend-open"
        type="button"
        data-status-legend-target="#{{ $legendId }}"
        aria-expanded="false"
        aria-controls="{{ $legendId }}"
    >
        <i class="bi bi-info-circle"></i> Status Legend
    </button>

    <div class="d-none mt-2 status-legend-panel" id="{{ $legendId }}">
        <div class="card bg-light">
            <div class="card-body p-2">
                <div class="status-legend-header mb-2">
                    <div class="status-legend-title">{{ $legendTitle }}</div>
                    <button
                        type="button"
                        class="status-legend-close js-status-legend-close"
                        data-status-legend-target="#{{ $legendId }}"
                        aria-label="Close status legend"
                    >
                        &times;
                    </button>
                </div>
                <div class="status-legend-list">
                    @foreach($legendItems as $item)
                        <div class="status-legend-item">
                            <div>
                                <span class="status-legend-label">Status</span>
                                <span class="{{ $item['badge_class'] ?? 'badge badge-secondary' }}">
                                    {{ $item['status'] ?? '-' }}
                                </span>
                            </div>
                            <div>
                                <span class="status-legend-label">Meaning</span>
                                <div class="status-legend-text">{{ $item['meaning'] ?? '-' }}</div>
                            </div>
                            <div>
                                <span class="status-legend-label">Trigger</span>
                                <div class="status-legend-text">{{ $item['trigger'] ?? '-' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
