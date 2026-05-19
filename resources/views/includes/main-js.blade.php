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
        var confirmedForms = new WeakSet();
        var confirmedClicks = new WeakSet();
        var modalConfirmHandler = null;
        var modalCancelHandler = null;

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

        function dataOption(form, submitter, key, fallback) {
            var value = submitter && submitter.getAttribute(key);
            if (value === null || value === '') value = form && form.getAttribute(key);
            return value === null || value === '' ? fallback : value;
        }

        function clearPendingState(form) {
            if (!form) return;
            form.removeAttribute('data-confirm-submit-pending');
        }

        function hasEnabledConfirm(form, submitter) {
            if (!isFormElement(form)) return false;
            if (form.getAttribute('data-confirm-submit') === 'false') return false;
            if (form.getAttribute('data-no-confirm-submit') === 'true') return false;

            return form.getAttribute('data-confirm-submit') === 'true'
                || form.getAttribute('data-delivery-confirm-submit') === 'true'
                || (submitter && submitter.getAttribute('data-confirm-submit-button') === 'true');
        }

        function getModal() {
            var modal = document.getElementById('confirmSubmitModal');
            if (!modal) return null;

            return {
                root: modal,
                title: document.getElementById('confirmSubmitModalTitle'),
                message: document.getElementById('confirmSubmitModalMessage'),
                confirm: document.getElementById('confirmSubmitModalConfirm'),
                cancel: document.getElementById('confirmSubmitModalCancel')
            };
        }

        function openModal(modal) {
            if (window.coreui && window.coreui.Modal && typeof window.coreui.Modal.getOrCreateInstance === 'function') {
                window.coreui.Modal.getOrCreateInstance(modal).show();
                return;
            }

            if (window.bootstrap && window.bootstrap.Modal && typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
                return;
            }

            if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
                window.jQuery(modal).modal('show');
                return;
            }

            modal.classList.add('show');
            modal.style.display = 'block';
            modal.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        function closeModal(modal) {
            if (window.coreui && window.coreui.Modal && typeof window.coreui.Modal.getOrCreateInstance === 'function') {
                window.coreui.Modal.getOrCreateInstance(modal).hide();
                return;
            }

            if (window.bootstrap && window.bootstrap.Modal && typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                return;
            }

            if (window.jQuery && typeof window.jQuery(modal).modal === 'function') {
                window.jQuery(modal).modal('hide');
                return;
            }

            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            if (typeof modalCancelHandler === 'function') modalCancelHandler();
        }

        function setConfirmVariant(button, variant) {
            var allowed = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
            variant = allowed.indexOf(variant) !== -1 ? variant : 'primary';
            button.className = 'btn btn-' + variant;
        }

        function showConfirmModal(options) {
            var modal = getModal();
            if (!modal || !modal.root || !modal.confirm || !modal.cancel) return;

            modal.title.textContent = options.title || 'Confirm Action';
            modal.message.textContent = options.message || 'Please make sure all information is correct before continuing.';
            modal.confirm.textContent = options.confirmText || 'Confirm';
            modal.cancel.textContent = options.cancelText || 'Cancel';
            modal.confirm.disabled = false;
            modal.cancel.classList.toggle('d-none', !!options.noticeOnly);
            setConfirmVariant(modal.confirm, options.variant || 'primary');

            if (modalConfirmHandler) modal.confirm.removeEventListener('click', modalConfirmHandler);
            modalConfirmHandler = function () {
                modal.confirm.disabled = true;
                if (typeof options.onConfirm === 'function') options.onConfirm();
            };
            modal.confirm.addEventListener('click', modalConfirmHandler);

            modalCancelHandler = function () {
                modal.confirm.disabled = false;
                if (typeof options.onCancel === 'function') options.onCancel();
                modalCancelHandler = null;
            };

            openModal(modal.root);
        }

        window.showConfirmSubmitModal = function (options) {
            return new Promise(function (resolve) {
                showConfirmModal({
                    title: options && options.title,
                    message: options && options.message,
                    confirmText: options && (options.confirmText || options.confirmButtonText),
                    cancelText: options && (options.cancelText || options.cancelButtonText),
                    variant: options && options.variant,
                    onConfirm: function () {
                        closeModal(getModal().root);
                        resolve(true);
                    },
                    onCancel: function () {
                        resolve(false);
                    }
                });
            });
        };

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
            if (submitter.hasAttribute('formaction')) form.action = submitter.formAction;
            if (submitter.hasAttribute('formmethod')) form.method = submitter.formMethod;
            if (submitter.hasAttribute('formenctype')) form.enctype = submitter.formEnctype;
            if (submitter.hasAttribute('formtarget')) form.target = submitter.formTarget;
        }

        function setSubmitterLoading(submitter, form) {
            if (!submitter || submitter.disabled) return;

            submitter.disabled = true;

            var loadingText = dataOption(form, submitter, 'data-confirm-loading-text', 'Processing...');
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
            var validSubmitter = getValidSubmitter(form, submitter);

            confirmedForms.add(form);
            form.setAttribute('data-confirmed-submit', 'true');
            form.setAttribute('data-confirm-submit-resubmitting', 'true');
            clearPendingState(form);
            closeModal(getModal().root);

            if (typeof form.requestSubmit === 'function') {
                try {
                    if (validSubmitter) {
                        form.requestSubmit(validSubmitter);
                    } else {
                        form.requestSubmit();
                    }
                    return;
                } catch (e) {
                    try {
                        form.requestSubmit();
                        return;
                    } catch (requestSubmitError) {}
                }
            }

            appendSubmitterValue(form, validSubmitter);
            applySubmitterOverrides(form, validSubmitter);
            setSubmitterLoading(validSubmitter, form);
            HTMLFormElement.prototype.submit.call(form);
        }

        function hasPurchaseDeliveryQuantity(form) {
            var quantityInputs = form.querySelectorAll('input[name^="quantity["], .qty-input');

            return Array.prototype.some.call(quantityInputs, function (input) {
                if (input.disabled) return false;
                var value = parseFloat(input.value || '0');
                return !Number.isNaN(value) && value > 0;
            });
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

        function hasRequiredItemRows(form) {
            var mode = form.getAttribute('data-item-validation');
            if (mode === 'purchase-delivery') return hasPurchaseDeliveryQuantity(form);
            if (mode === 'sale-delivery') return hasSaleDeliveryQuantity(form);

            var quantityInput = form.querySelector('input[name="total_quantity"]');
            if (quantityInput && parseInt(quantityInput.value || '0', 10) > 0) return true;

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

        function showRequiredItemsWarning(form) {
            showConfirmModal({
                title: dataOption(form, null, 'data-confirm-items-title', 'Product Required'),
                message: dataOption(form, null, 'data-confirm-items-message', 'Please add at least one product before submitting this document.'),
                confirmText: dataOption(form, null, 'data-confirm-items-button-text', 'OK'),
                cancelText: 'Cancel',
                variant: 'primary',
                noticeOnly: true,
                onConfirm: function () {
                    closeModal(getModal().root);
                    clearPendingState(form);
                },
                onCancel: function () {
                    clearPendingState(form);
                }
            });
        }

        function saleDeliveryConfirmIsValid(form) {
            if (form.getAttribute('data-delivery-validation') !== 'sale-delivery-confirm') return true;

            if (typeof window.refreshSaleDeliveryConfirmValidation === 'function') {
                window.refreshSaleDeliveryConfirmValidation();
            }

            return !Array.prototype.some.call(document.querySelectorAll('.confirm-card'), function (card) {
                return typeof window.updateSaleDeliveryConfirmCard === 'function'
                    ? !window.updateSaleDeliveryConfirmCard(card)
                    : card.classList.contains('border-danger');
            });
        }

        function showConfirmation(form, submitter) {
            var title = dataOption(form, submitter, 'data-confirm-title', 'Confirm Action');
            var message = dataOption(form, submitter, 'data-confirm-message', 'Please make sure all information is correct before continuing.');
            var confirmText = dataOption(form, submitter, 'data-confirm-button', null)
                || dataOption(form, submitter, 'data-confirm-confirm-text', 'Confirm');
            var cancelText = dataOption(form, submitter, 'data-confirm-cancel', null)
                || dataOption(form, submitter, 'data-confirm-cancel-text', 'Cancel');
            var variant = dataOption(form, submitter, 'data-confirm-variant', 'primary');

            showConfirmModal({
                title: title,
                message: message,
                confirmText: confirmText,
                cancelText: cancelText,
                variant: variant,
                onConfirm: function () {
                    submitConfirmed(form, submitter);
                },
                onCancel: function () {
                    clearPendingState(form);
                }
            });
        }

        document.addEventListener('hidden.bs.modal', function (event) {
            if (event.target && event.target.id === 'confirmSubmitModal' && typeof modalCancelHandler === 'function') {
                modalCancelHandler();
            }
        });

        document.addEventListener('hidden.coreui.modal', function (event) {
            if (event.target && event.target.id === 'confirmSubmitModal' && typeof modalCancelHandler === 'function') {
                modalCancelHandler();
            }
        });

        document.addEventListener('click', function (event) {
            var submitter = getSubmitButton(event.target);
            if (submitter) lastSubmitter = submitter;

            var formTarget = event.target && event.target.closest ? event.target.closest('[data-confirm-target-form]') : null;
            if (formTarget) {
                event.preventDefault();
                event.stopImmediatePropagation();

                var targetForm = document.getElementById(formTarget.getAttribute('data-confirm-target-form'));
                if (!targetForm) return;

                showConfirmModal({
                    title: formTarget.getAttribute('data-confirm-title') || 'Confirm Action',
                    message: formTarget.getAttribute('data-confirm-message') || 'Please make sure all information is correct before continuing.',
                    confirmText: formTarget.getAttribute('data-confirm-button') || formTarget.getAttribute('data-confirm-confirm-text') || 'Confirm',
                    cancelText: formTarget.getAttribute('data-confirm-cancel') || formTarget.getAttribute('data-confirm-cancel-text') || 'Cancel',
                    variant: formTarget.getAttribute('data-confirm-variant') || 'primary',
                    onConfirm: function () {
                        submitConfirmed(targetForm, null);
                    },
                    onCancel: function () {
                        clearPendingState(targetForm);
                    }
                });
                return;
            }

            var confirmTarget = event.target && event.target.closest ? event.target.closest('[data-confirm-click="true"]') : null;
            if (!confirmTarget || confirmedClicks.has(confirmTarget)) {
                if (confirmTarget) confirmedClicks.delete(confirmTarget);
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            showConfirmModal({
                title: confirmTarget.getAttribute('data-confirm-title') || 'Confirm Action',
                message: confirmTarget.getAttribute('data-confirm-message') || 'Please make sure all information is correct before continuing.',
                confirmText: confirmTarget.getAttribute('data-confirm-button') || confirmTarget.getAttribute('data-confirm-confirm-text') || 'Confirm',
                cancelText: confirmTarget.getAttribute('data-confirm-cancel') || confirmTarget.getAttribute('data-confirm-cancel-text') || 'Cancel',
                variant: confirmTarget.getAttribute('data-confirm-variant') || 'primary',
                onConfirm: function () {
                    confirmedClicks.add(confirmTarget);
                    closeModal(getModal().root);
                    confirmTarget.click();
                }
            });
        }, true);

        document.addEventListener('submit', function (event) {
            var form = event.target;
            var submitter = getValidSubmitter(form, event.submitter || lastSubmitter);

            if (!hasEnabledConfirm(form, submitter)) return;

            if (confirmedForms.has(form) || form.getAttribute('data-confirmed-submit') === 'true') {
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
            event.stopImmediatePropagation();

            if (form.getAttribute('data-confirm-submit-pending') === 'true') return;
            form.setAttribute('data-confirm-submit-pending', 'true');
            if (!form.checkValidity()) {
                clearPendingState(form);
                form.reportValidity();
                return;
            }

            if (form.getAttribute('data-confirm-require-items') === 'true' && !hasRequiredItemRows(form)) {
                showRequiredItemsWarning(form);
                return;
            }

            if (!saleDeliveryConfirmIsValid(form)) {
                clearPendingState(form);
                var box = document.getElementById('rowErrorBox');
                if (box) box.classList.remove('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
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
