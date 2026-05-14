<script src="{{ mix('js/app.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/1.4.0/perfect-scrollbar.js"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>

@include('sweetalert::alert')

@yield('third_party_scripts')

@livewireScripts

@include('includes.session-timeout')

<script>
    (function () {
        var lastSubmitter = null;
        var deliveryPendingForm = null;

        function isFormElement(element) {
            return element && element.tagName && element.tagName.toLowerCase() === 'form';
        }

        function getSubmitButton(target) {
            if (!target || !target.closest) return null;

            var button = target.closest('button, input');
            if (!button || !button.form) return null;

            var tag = (button.tagName || '').toLowerCase();
            var type = ((button.getAttribute('type') || button.type || 'submit') + '').toLowerCase();

            if (tag === 'button' && (type === '' || type === 'submit')) return button;
            if (tag === 'input' && ['submit', 'image'].indexOf(type) !== -1) return button;

            return null;
        }

        function getValidSubmitter(form, submitter) {
            if (!submitter || submitter.form !== form) return null;
            if (!document.documentElement.contains(submitter)) return null;

            var tag = (submitter.tagName || '').toLowerCase();
            var type = ((submitter.getAttribute('type') || submitter.type || 'submit') + '').toLowerCase();

            if (tag === 'button' && (type === '' || type === 'submit')) return submitter;
            if (tag === 'input' && ['submit', 'image'].indexOf(type) !== -1) return submitter;

            return null;
        }

        function clearPendingState(form) {
            form.removeAttribute('data-confirm-submit-pending');
        }

        function isConfirmedSubmit(form) {
            return form.getAttribute('data-confirmed-submit') === 'true';
        }

        function hasEnabledConfirm(form, submitter) {
            if (!isFormElement(form)) return false;
            if (form.getAttribute('data-confirm-submit') === 'false') return false;
            if (form.getAttribute('data-no-confirm-submit') === 'true') return false;

            return form.getAttribute('data-confirm-submit') === 'true'
                || (submitter && submitter.getAttribute('data-confirm-submit-button') === 'true');
        }

        function getOption(form, name, fallback) {
            var value = form.getAttribute(name);
            return value === null || value === '' ? fallback : value;
        }

        function getSweetAlert() {
            if (window.Swal && typeof window.Swal.fire === 'function') return window.Swal;
            if (window.Sweetalert2 && typeof window.Sweetalert2.fire === 'function') return window.Sweetalert2;
            if (window.swal && typeof window.swal.fire === 'function') return window.swal;
            if (typeof window.swal === 'function') {
                return {
                    fire: function (options) {
                        return window.swal({
                            title: options.title,
                            text: options.text,
                            icon: options.icon,
                            buttons: [options.cancelButtonText, options.confirmButtonText]
                        }).then(function (confirmed) {
                            return { isConfirmed: !!confirmed };
                        });
                    }
                };
            }

            return null;
        }

        function appendSubmitterValue(form, submitter) {
            if (!submitter || !submitter.name || submitter.disabled) return null;

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = submitter.name;
            hidden.value = submitter.value || '';
            hidden.setAttribute('data-confirm-submit-generated', 'true');
            form.appendChild(hidden);

            return hidden;
        }

        function applySubmitterOverrides(form, submitter) {
            if (!submitter) return;

            if (submitter.hasAttribute('formaction')) {
                form.action = submitter.formAction;
            }

            if (submitter.hasAttribute('formmethod')) {
                form.method = submitter.formMethod;
            }

            if (submitter.hasAttribute('formenctype')) {
                form.enctype = submitter.formEnctype;
            }

            if (submitter.hasAttribute('formtarget')) {
                form.target = submitter.formTarget;
            }
        }

        function setSubmitterLoading(submitter, form) {
            if (!submitter || submitter.disabled) return;

            submitter.disabled = true;

            var loadingText = getOption(form, 'data-confirm-loading-text', 'Submitting...');
            var tag = (submitter.tagName || '').toLowerCase();
            var type = ((submitter.getAttribute('type') || submitter.type || '') + '').toLowerCase();

            if (tag === 'button') {
                submitter.setAttribute('data-confirm-original-html', submitter.innerHTML);
                submitter.innerHTML = loadingText;
            } else if (tag === 'input' && ['submit', 'button'].indexOf(type) !== -1) {
                submitter.setAttribute('data-confirm-original-value', submitter.value || '');
                submitter.value = loadingText;
            }
        }

        function submitConfirmed(form, submitter) {
            if (isConfirmedSubmit(form) && form.getAttribute('data-confirm-submit-resubmitting') === 'true') return;

            form.setAttribute('data-confirmed-submit', 'true');
            form.setAttribute('data-confirm-submit-resubmitting', 'true');
            clearPendingState(form);

            var validSubmitter = getValidSubmitter(form, submitter);

            if (typeof form.requestSubmit === 'function') {
                try {
                    if (validSubmitter) {
                        form.requestSubmit(validSubmitter);
                    } else {
                        form.requestSubmit();
                    }
                } catch (e) {
                    try {
                        form.requestSubmit();
                    } catch (requestSubmitError) {
                        appendSubmitterValue(form, validSubmitter);
                        applySubmitterOverrides(form, validSubmitter);
                        setSubmitterLoading(validSubmitter, form);
                        HTMLFormElement.prototype.submit.call(form);
                    }
                }
                return;
            }

            appendSubmitterValue(form, validSubmitter);
            applySubmitterOverrides(form, validSubmitter);
            setSubmitterLoading(validSubmitter, form);
            HTMLFormElement.prototype.submit.call(form);
        }

        function hasRequiredItemRows(form) {
            if (form.getAttribute('data-item-validation') === 'purchase-delivery') {
                return hasPurchaseDeliveryQuantity(form);
            }

            var quantityInput = form.querySelector('input[name="total_quantity"]');
            if (quantityInput && parseInt(quantityInput.value || '0', 10) > 0) {
                return true;
            }

            var itemSelectors = [
                '[data-cart-sync-row]',
                'input[name^="items"][name$="[product_id]"]',
                'input[name="product_ids[]"]',
                'input[name^="product_ids"]'
            ];

            return itemSelectors.some(function (selector) {
                return Array.prototype.some.call(form.querySelectorAll(selector), function (element) {
                    if (element.disabled) return false;
                    if (element.matches && element.matches('input, select, textarea')) {
                        return (element.value || '').trim() !== '' && (element.value || '0') !== '0';
                    }
                    return true;
                });
            });
        }

        function hasPurchaseDeliveryQuantity(form) {
            var quantityInputs = form.querySelectorAll('input[name^="quantity["], .qty-input');

            return Array.prototype.some.call(quantityInputs, function (input) {
                if (input.disabled) return false;

                var value = parseFloat(input.value || '0');
                return !Number.isNaN(value) && value > 0;
            });
        }

        function showRequiredItemsWarning(form) {
            var title = getOption(form, 'data-confirm-items-title', 'Product Required');
            var message = getOption(form, 'data-confirm-items-message', 'Please add at least one product before submitting this document.');
            var buttonText = getOption(form, 'data-confirm-items-button-text', 'OK');
            var Swal = getSweetAlert();

            if (Swal) {
                return Swal.fire({
                    title: title,
                    text: message,
                    icon: 'warning',
                    confirmButtonText: buttonText
                });
            }

            window.alert(title + '\n\n' + message);
        }

        function showConfirmation(form, submitter) {
            var title = getOption(form, 'data-confirm-title', 'Confirm Submit');
            var message = getOption(form, 'data-confirm-message', 'Are you sure you want to submit this form?');
            var confirmText = getOption(form, 'data-confirm-confirm-text', 'Yes, submit');
            var cancelText = getOption(form, 'data-confirm-cancel-text', 'Cancel');
            var icon = getOption(form, 'data-confirm-icon', 'question');

            var Swal = getSweetAlert();

            if (Swal) {
                return Swal.fire({
                    title: title,
                    text: message,
                    icon: icon,
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: cancelText,
                    reverseButtons: true,
                    focusCancel: true
                }).then(function (result) {
                    if (result && result.isConfirmed) {
                        submitConfirmed(form, submitter);
                    } else {
                        clearPendingState(form);
                    }
                }).catch(function () {
                    clearPendingState(form);
                });
            }

            if (window.confirm(title + '\n\n' + message)) {
                submitConfirmed(form, submitter);
            } else {
                clearPendingState(form);
            }
        }

        function ensureDeliveryConfirmModal() {
            var existing = document.getElementById('deliveryConfirmModal');
            if (existing) return existing;

            var modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'deliveryConfirmModal';
            modal.tabIndex = -1;
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-hidden', 'true');
            modal.innerHTML = '' +
                '<div class="modal-dialog modal-dialog-centered" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title" id="deliveryConfirmTitle">Confirm Submit</h5>' +
                            '<button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="mb-0" id="deliveryConfirmMessage">Please review before submitting.</p>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal" id="deliveryConfirmCancel">Cancel</button>' +
                            '<button type="button" class="btn btn-primary" id="deliveryConfirmOk">Submit</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(modal);
            return modal;
        }

        function openDeliveryModal(modal) {
            if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
                window.jQuery(modal).modal('show');
                return;
            }

            modal.classList.add('show');
            modal.style.display = 'block';
            modal.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        function closeDeliveryModal(modal) {
            if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
                window.jQuery(modal).modal('hide');
                return;
            }

            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        function showDeliveryNotice(form, title, message) {
            var modal = ensureDeliveryConfirmModal();
            modal.querySelector('#deliveryConfirmTitle').textContent = title;
            modal.querySelector('#deliveryConfirmMessage').textContent = message;
            modal.querySelector('#deliveryConfirmCancel').classList.add('d-none');

            var ok = modal.querySelector('#deliveryConfirmOk');
            ok.textContent = getOption(form, 'data-confirm-items-button-text', 'OK');
            ok.className = 'btn btn-primary';
            ok.onclick = function () {
                closeDeliveryModal(modal);
                clearPendingState(form);
                modal.querySelector('#deliveryConfirmCancel').classList.remove('d-none');
            };

            openDeliveryModal(modal);
        }

        function hasSaleDeliveryQuantity(form) {
            var rows = form.querySelectorAll('input[name^="items"][name$="[product_id]"], select[name^="items"][name$="[product_id]"]');

            return Array.prototype.some.call(rows, function (productInput) {
                if (productInput.disabled) return false;

                var match = String(productInput.name || '').match(/^items\[(.+?)\]\[product_id\]$/);
                if (!match) return false;

                var quantityInput = form.querySelector('[name="items[' + match[1] + '][quantity]"]');
                if (!quantityInput || quantityInput.disabled) return false;

                var productId = parseInt(productInput.value || '0', 10);
                var quantity = parseFloat(quantityInput.value || '0');

                return productId > 0 && !Number.isNaN(quantity) && quantity > 0;
            });
        }

        function deliveryFormHasRequiredItems(form) {
            var mode = form.getAttribute('data-item-validation');

            if (mode === 'purchase-delivery') {
                return hasPurchaseDeliveryQuantity(form);
            }

            if (mode === 'sale-delivery') {
                return hasSaleDeliveryQuantity(form);
            }

            return true;
        }

        function saleDeliveryConfirmIsValid(form) {
            if (form.getAttribute('data-delivery-validation') !== 'sale-delivery-confirm') {
                return true;
            }

            if (typeof window.refreshSaleDeliveryConfirmValidation === 'function') {
                window.refreshSaleDeliveryConfirmValidation();
            }

            return !Array.prototype.some.call(document.querySelectorAll('.confirm-card'), function (card) {
                return typeof window.updateSaleDeliveryConfirmCard === 'function'
                    ? !window.updateSaleDeliveryConfirmCard(card)
                    : card.classList.contains('border-danger');
            });
        }

        function showDeliveryConfirmation(form, submitter) {
            var modal = ensureDeliveryConfirmModal();
            modal.querySelector('#deliveryConfirmTitle').textContent = getOption(form, 'data-confirm-title', 'Confirm Submit');
            modal.querySelector('#deliveryConfirmMessage').textContent = getOption(form, 'data-confirm-message', 'Please review before submitting.');
            modal.querySelector('#deliveryConfirmCancel').classList.remove('d-none');

            var ok = modal.querySelector('#deliveryConfirmOk');
            ok.textContent = getOption(form, 'data-confirm-confirm-text', 'Submit');
            ok.className = 'btn btn-primary';
            deliveryPendingForm = form;
            ok.onclick = function () {
                closeDeliveryModal(modal);

                form.setAttribute('data-delivery-confirmed-submit', 'true');
                clearPendingState(form);
                appendSubmitterValue(form, getValidSubmitter(form, submitter));
                applySubmitterOverrides(form, getValidSubmitter(form, submitter));
                setSubmitterLoading(getValidSubmitter(form, submitter), form);
                HTMLFormElement.prototype.submit.call(form);
            };

            var cancel = modal.querySelector('#deliveryConfirmCancel');
            cancel.onclick = function () {
                clearPendingState(form);
                deliveryPendingForm = null;
            };

            openDeliveryModal(modal);
        }

        document.addEventListener('hidden.bs.modal', function (event) {
            if (event.target && event.target.id === 'deliveryConfirmModal' && deliveryPendingForm) {
                clearPendingState(deliveryPendingForm);
                deliveryPendingForm = null;
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!isFormElement(form) || form.getAttribute('data-delivery-confirm-submit') !== 'true') {
                return;
            }

            if (form.getAttribute('data-delivery-confirmed-submit') === 'true') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (form.getAttribute('data-confirm-submit-pending') === 'true') return;
            form.setAttribute('data-confirm-submit-pending', 'true');

            if (!form.checkValidity()) {
                clearPendingState(form);
                form.reportValidity();
                return;
            }

            if (form.getAttribute('data-confirm-require-items') === 'true' && !deliveryFormHasRequiredItems(form)) {
                showDeliveryNotice(
                    form,
                    getOption(form, 'data-confirm-items-title', 'Item Quantity Required'),
                    getOption(form, 'data-confirm-items-message', 'Please input at least one item quantity before submitting this delivery.')
                );
                clearPendingState(form);
                return;
            }

            if (!saleDeliveryConfirmIsValid(form)) {
                clearPendingState(form);
                var box = document.getElementById('rowErrorBox');
                if (box) box.classList.remove('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            showDeliveryConfirmation(form, getValidSubmitter(form, event.submitter || lastSubmitter));
        }, true);

        document.addEventListener('click', function (event) {
            var submitter = getSubmitButton(event.target);
            if (submitter) {
                lastSubmitter = submitter;
            }
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;
            var submitter = getValidSubmitter(form, event.submitter || lastSubmitter);

            if (!hasEnabledConfirm(form, submitter)) return;
            if (isConfirmedSubmit(form)) {
                clearPendingState(form);
                applySubmitterOverrides(form, submitter);

                window.setTimeout(function () {
                    if (!event.defaultPrevented) {
                        form.removeAttribute('data-confirmed-submit');
                        form.removeAttribute('data-confirm-submit-resubmitting');
                        setSubmitterLoading(submitter, form);
                    } else {
                        form.removeAttribute('data-confirm-submit-resubmitting');
                    }
                }, 0);

                return;
            }

            event.preventDefault();

            if (form.getAttribute('data-confirm-submit-pending') === 'true') return;
            form.setAttribute('data-confirm-submit-pending', 'true');

            if (form.getAttribute('data-confirm-require-items') === 'true' && !hasRequiredItemRows(form)) {
                showRequiredItemsWarning(form);
                clearPendingState(form);
                return;
            }

            var beforeEvent = new CustomEvent('confirm-submit:before', {
                bubbles: true,
                cancelable: true,
                detail: {
                    form: form,
                    submitter: submitter
                }
            });

            if (!form.dispatchEvent(beforeEvent)) {
                clearPendingState(form);
                return;
            }

            showConfirmation(form, submitter);
        }, true);
    })();
</script>

<script>
    (function () {
        if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
            jQuery.extend(true, jQuery.fn.dataTable.defaults, {
                scrollX: true,
                responsive: false,
                autoWidth: false
            });

            var adjustTimer = null;

            function markDataTableScrollContainers(table) {
                var $table = table ? jQuery(table) : jQuery('table.dataTable');

                $table.each(function () {
                    jQuery(this)
                        .closest('.table-responsive, .table-wrap')
                        .addClass('dt-scroll-container');
                });
            }

            window.adjustVisibleDataTables = function () {
                if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) return;

                try {
                    markDataTableScrollContainers();

                    jQuery.fn.dataTable
                        .tables({ visible: true, api: true })
                        .columns.adjust();
                } catch (e) {
                    // Keep global layout recovery best-effort.
                }
            };

            function scheduleDataTablesAdjust(delay) {
                window.clearTimeout(adjustTimer);
                adjustTimer = window.setTimeout(window.adjustVisibleDataTables, delay || 80);
            }

            jQuery(document)
                .on('init.dt draw.dt', function (event, settings) {
                    if (settings && settings.nTable) {
                        markDataTableScrollContainers(settings.nTable);
                    }

                    scheduleDataTablesAdjust(80);
                })
                .on('shown.bs.tab shown.coreui.tab', function () {
                    scheduleDataTablesAdjust(80);
                })
                .on('click', '[data-coreui-toggle="collapse"], [data-toggle="collapse"], .sidebar-toggler, .navbar-toggler, .c-header-toggler, .c-sidebar-toggler', function () {
                    scheduleDataTablesAdjust(350);
                });

            jQuery(window).on('resize orientationchange', function () {
                scheduleDataTablesAdjust(150);
            });

            if (window.MutationObserver && document.body) {
                new MutationObserver(function () {
                    scheduleDataTablesAdjust(250);
                }).observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            window.setTimeout(window.adjustVisibleDataTables, 250);
        }
    })();
</script>

@stack('page_scripts')

<script>
    (function () {
        function isInteractiveRowTarget(event) {
            var $target = $(event.target);

            if ($target.closest('a, button, input, select, textarea, label, form, .dropdown, .dropdown-menu, [role="button"], [data-toggle], [data-bs-toggle], .dt-control, .dtr-control').length) {
                return true;
            }

            var $cell = $target.closest('td, th');
            if ($cell.length && $cell.find('a, button, input, select, textarea, form, .dropdown, [role="button"], [data-toggle], [data-bs-toggle]').length) {
                return true;
            }

            return window.getSelection && window.getSelection().toString().length > 0;
        }

        $(document).on('click', 'table tbody tr[data-href]', function (event) {
            if (isInteractiveRowTarget(event)) {
                return;
            }

            var href = $(this).data('href');
            if (href) {
                window.location.href = href;
            }
        });
    })();
</script>

<script>
    (function () {
        if (!window.jQuery) return;

        var activeDropdown = null;
        var portalClass = 'dt-dropdown-portal';
        var portalReadyClass = 'dt-dropdown-portal-ready';
        var viewportMargin = 8;
        var portalTimer = null;

        function getDropdownContext(element) {
            var $context = jQuery(element).closest('.dataTables_wrapper, .table-responsive, .table-wrap');

            if (!$context.length) return null;

            if ($context.hasClass('dataTables_wrapper') || $context.find('table.dataTable').length) {
                return $context[0];
            }

            return null;
        }

        function getDropdownToggle(dropdown, event) {
            if (event && event.relatedTarget && event.relatedTarget.matches && event.relatedTarget.matches('[data-toggle="dropdown"], [data-bs-toggle="dropdown"], .dropdown-toggle, button.dropdown')) {
                return event.relatedTarget;
            }

            if (dropdown && dropdown.matches && dropdown.matches('[data-toggle="dropdown"], [data-bs-toggle="dropdown"], .dropdown-toggle, button.dropdown')) {
                return dropdown;
            }

            return dropdown ? dropdown.querySelector('[data-toggle="dropdown"], [data-bs-toggle="dropdown"], .dropdown-toggle, button.dropdown') : null;
        }

        function getDropdownRoot(toggle, eventTarget) {
            var root = null;

            if (eventTarget && eventTarget.matches && eventTarget.matches('.dropdown, .btn-group, .dropleft, .dropright, .dropup')) {
                root = eventTarget;
            }

            if (!root && toggle) {
                root = toggle.closest('.dropdown, .btn-group, .dropleft, .dropright, .dropup');
            }

            return root;
        }

        function restorePortalDropdown() {
            if (!activeDropdown) return;

            var menu = activeDropdown.menu;
            var placeholder = activeDropdown.placeholder;

            menu.classList.remove(portalClass);
            menu.classList.remove(portalReadyClass);
            menu.style.removeProperty('position');
            menu.style.removeProperty('inset');
            menu.style.removeProperty('left');
            menu.style.removeProperty('top');
            menu.style.removeProperty('right');
            menu.style.removeProperty('bottom');
            menu.style.removeProperty('margin');
            menu.style.removeProperty('transform');
            menu.style.removeProperty('max-height');
            menu.style.removeProperty('overflow-y');
            menu.style.removeProperty('overflow-x');

            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(menu, placeholder);
                placeholder.parentNode.removeChild(placeholder);
            } else if (menu.parentNode === document.body) {
                menu.parentNode.removeChild(menu);
            }

            activeDropdown = null;
        }

        function schedulePortal(toggle, eventTarget) {
            window.clearTimeout(portalTimer);
            portalTimer = window.setTimeout(function () {
                openPortalDropdown(toggle, eventTarget);
            }, 0);
        }

        function clamp(value, min, max) {
            if (max < min) return min;
            return Math.max(min, Math.min(value, max));
        }

        function positionPortalDropdown() {
            if (!activeDropdown) return;

            var toggle = activeDropdown.toggle;
            var menu = activeDropdown.menu;
            var dropdown = activeDropdown.dropdown;
            if (!toggle || !menu || !dropdown || !document.body.contains(menu)) return;

            var rect = toggle.getBoundingClientRect();
            var menuRect = menu.getBoundingClientRect();
            var menuWidth = menuRect.width || menu.offsetWidth || 220;
            var menuHeight = menuRect.height || menu.offsetHeight || 0;
            var availableHeight = Math.max(120, window.innerHeight - (viewportMargin * 2));
            var isDropLeft = dropdown.classList.contains('dropleft');
            var isDropRight = dropdown.classList.contains('dropright');
            var isDropUp = dropdown.classList.contains('dropup');
            var alignRight = menu.classList.contains('dropdown-menu-right');
            var left;
            var top;

            if (isDropLeft) {
                left = rect.left - menuWidth - 2;
                if (left < viewportMargin) {
                    left = rect.right + 2;
                }
                top = rect.top;
            } else if (isDropRight) {
                left = rect.right + 2;
                if (left + menuWidth > window.innerWidth - viewportMargin) {
                    left = rect.left - menuWidth - 2;
                }
                top = rect.top;
            } else {
                left = alignRight ? rect.right - menuWidth : rect.left;
                top = isDropUp ? rect.top - menuHeight - 2 : rect.bottom + 2;
            }

            if (!isDropUp && top + menuHeight > window.innerHeight - viewportMargin) {
                top = Math.max(viewportMargin, rect.bottom - menuHeight);
            }

            if (isDropUp && top < viewportMargin) {
                top = rect.bottom + 2;
            }

            if (menuHeight > availableHeight) {
                menu.style.setProperty('max-height', availableHeight + 'px', 'important');
                menu.style.setProperty('overflow-y', 'auto', 'important');
                menu.style.setProperty('overflow-x', 'hidden', 'important');
                menuHeight = availableHeight;
            } else {
                menu.style.removeProperty('max-height');
                menu.style.removeProperty('overflow-y');
                menu.style.removeProperty('overflow-x');
            }

            left = clamp(left, viewportMargin, window.innerWidth - menuWidth - viewportMargin);
            top = clamp(top, viewportMargin, window.innerHeight - menuHeight - viewportMargin);

            menu.style.setProperty('position', 'fixed', 'important');
            menu.style.setProperty('inset', 'auto', 'important');
            menu.style.setProperty('left', left + 'px', 'important');
            menu.style.setProperty('top', top + 'px', 'important');
            menu.style.setProperty('right', 'auto', 'important');
            menu.style.setProperty('bottom', 'auto', 'important');
            menu.style.setProperty('margin', '0', 'important');
            menu.style.setProperty('transform', 'none', 'important');
            menu.classList.add(portalReadyClass);
        }

        function openPortalDropdown(toggle, eventTarget) {
            var dropdown = getDropdownRoot(toggle, eventTarget);
            if (!dropdown || !toggle || !getDropdownContext(dropdown)) return;

            var menu = dropdown.querySelector('.dropdown-menu');
            if (!menu) return;

            var isOpen = dropdown.classList.contains('show') || menu.classList.contains('show') || window.getComputedStyle(menu).display !== 'none';
            if (!isOpen) return;

            if (activeDropdown && activeDropdown.menu === menu) {
                positionPortalDropdown();
                return;
            }

            restorePortalDropdown();

            var placeholder = document.createComment('datatable-dropdown-placeholder');
            menu.parentNode.insertBefore(placeholder, menu);

            activeDropdown = {
                dropdown: dropdown,
                menu: menu,
                toggle: toggle,
                placeholder: placeholder
            };

            document.body.appendChild(menu);
            menu.classList.add(portalClass);
            positionPortalDropdown();

            window.setTimeout(positionPortalDropdown, 50);
        }

        function prepareDataTableDropdown(event) {
            var target = event.target;
            var toggle = getDropdownToggle(target, event);

            if (!toggle || !getDropdownContext(toggle)) return;

            toggle.setAttribute('data-boundary', 'viewport');
            toggle.setAttribute('data-reference', 'toggle');
            schedulePortal(toggle, target);
        }

        function handleShownDropdown(event) {
            var toggle = getDropdownToggle(event.target, event);

            if (!toggle && event.target && event.target.querySelector) {
                toggle = event.target.querySelector('[data-toggle="dropdown"], [data-bs-toggle="dropdown"], .dropdown-toggle, button.dropdown');
            }

            openPortalDropdown(toggle, event.target);
        }

        function handleHideDropdown(event) {
            if (!activeDropdown) return;

            var target = event.target;
            var isActiveDropdownEvent = activeDropdown.dropdown === target
                || activeDropdown.toggle === target
                || (target && target.contains && target.contains(activeDropdown.toggle));

            if (!isActiveDropdownEvent) return;

            restorePortalDropdown();
        }

        function handleDocumentClick(event) {
            if (!activeDropdown) return;

            var target = event.target;
            var clickedInsideMenu = activeDropdown.menu.contains(target);
            var clickedToggle = activeDropdown.toggle === target || activeDropdown.toggle.contains(target);

            if (clickedInsideMenu || clickedToggle) return;

            window.setTimeout(function () {
                if (!activeDropdown) return;

                var dropdownIsOpen = activeDropdown.dropdown.classList.contains('show')
                    || activeDropdown.menu.classList.contains('show');

                if (!dropdownIsOpen) {
                    restorePortalDropdown();
                }
            }, 0);
        }

        jQuery(document)
            .on('click', '[data-toggle="dropdown"], [data-bs-toggle="dropdown"], .dropdown-toggle, button.dropdown', prepareDataTableDropdown)
            .on('show.bs.dropdown show.coreui.dropdown', prepareDataTableDropdown)
            .on('shown.bs.dropdown shown.coreui.dropdown', handleShownDropdown)
            .on('hide.bs.dropdown hide.coreui.dropdown hidden.bs.dropdown hidden.coreui.dropdown', handleHideDropdown)
            .on('click', handleDocumentClick);

        jQuery(document).on('draw.dt page.dt length.dt search.dt order.dt', restorePortalDropdown);
        jQuery(window).on('scroll resize orientationchange', positionPortalDropdown);
        jQuery(document).on('scroll', '.dataTables_scrollBody, .table-responsive, .table-wrap', positionPortalDropdown);

        if (document.addEventListener) {
            document.addEventListener('scroll', positionPortalDropdown, true);
        }
    })();
</script>
