<script src="{{ mix('js/app.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/1.4.0/perfect-scrollbar.js"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>

@include('sweetalert::alert')

@yield('third_party_scripts')

@livewireScripts

@include('includes.session-timeout')

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
