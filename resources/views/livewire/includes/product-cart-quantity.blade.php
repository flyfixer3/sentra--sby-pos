<input
    wire:model.defer="quantity.{{ !empty($rowAwareSaleCart) ? $lineKey : $cart_item->id }}"
    wire:change="updateQuantity('{{ $cart_item->rowId }}', '{{ $cart_item->id }}'{{ !empty($rowAwareSaleCart) ? ", '" . $lineKey . "'" : '' }})"
    data-cart-sync-row
    data-cart-sync-field="quantity"
    data-cart-row-id="{{ $cart_item->rowId }}"
    data-cart-product-id="{{ $cart_item->id }}"
    @if(!empty($rowAwareSaleCart))
        data-cart-line-key="{{ $lineKey }}"
    @endif
    style="min-width: 40px;max-width: 90px;"
    type="number"
    class="form-control"
    value="{{ $cart_item->qty }}"
    min="1"
>

@once
    <script>
        (function () {
            if (window.__cartFinalSyncBound) {
                return;
            }

            window.__cartFinalSyncBound = true;

            function collectCartPayload(componentEl) {
                var rows = {};

                componentEl.querySelectorAll('[data-cart-sync-row]').forEach(function (input) {
                    var productId = input.getAttribute('data-cart-product-id');
                    if (!productId) {
                        return;
                    }

                    var lineKey = input.getAttribute('data-cart-line-key') || '';
                    var payloadKey = lineKey || productId;

                    if (!rows[payloadKey]) {
                        rows[payloadKey] = {
                            product_id: productId,
                            row_id: input.getAttribute('data-cart-row-id'),
                            line_key: lineKey
                        };
                    }

                    rows[payloadKey][input.getAttribute('data-cart-sync-field')] = input.value;
                });

                return Object.keys(rows).map(function (key) {
                    return rows[key];
                });
            }

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (!form || !form.querySelector('[data-cart-sync-row]')) {
                    return;
                }

                if (form.dataset.cartFinalSynced === '1') {
                    delete form.dataset.cartFinalSynced;
                    return;
                }

                var componentEls = Array.prototype.slice.call(form.querySelectorAll('[wire\\:id]')).filter(function (componentEl) {
                    return componentEl.querySelector('[data-cart-sync-row]');
                });

                if (!componentEls.length || !window.Livewire || typeof window.Livewire.find !== 'function') {
                    return;
                }

                event.preventDefault();

                var submitter = event.submitter || document.activeElement;
                if (submitter && submitter.disabled !== undefined) {
                    submitter.disabled = true;
                }

                var syncCalls = componentEls.map(function (componentEl) {
                    var component = window.Livewire.find(componentEl.getAttribute('wire:id'));
                    if (!component || typeof component.call !== 'function') {
                        return Promise.resolve();
                    }

                    return component.call('finalizeCartBeforeSubmit', collectCartPayload(componentEl));
                });

                Promise.all(syncCalls).then(function () {
                    form.dataset.cartFinalSynced = '1';

                    if (submitter && submitter.disabled !== undefined) {
                        submitter.disabled = false;
                    }

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitter && submitter.type === 'submit' ? submitter : undefined);
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                }).catch(function () {
                    if (submitter && submitter.disabled !== undefined) {
                        submitter.disabled = false;
                    }
                });
            }, true);
        })();
    </script>
@endonce
