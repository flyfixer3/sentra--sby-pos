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

        function positionPortalDropdown() {
            if (!activeDropdown) return;

            var toggle = activeDropdown.toggle;
            var menu = activeDropdown.menu;
            if (!toggle || !menu || !document.body.contains(menu)) return;

            var rect = toggle.getBoundingClientRect();
            var menuWidth = menu.offsetWidth || 220;
            var menuHeight = menu.offsetHeight || 0;
            var margin = 8;
            var left = rect.left - menuWidth;

            if (left < margin) {
                left = rect.right;
            }

            if (left + menuWidth > window.innerWidth - margin) {
                left = Math.max(margin, window.innerWidth - menuWidth - margin);
            }

            var top = rect.top;
            if (top + menuHeight > window.innerHeight - margin) {
                top = Math.max(margin, window.innerHeight - menuHeight - margin);
            }

            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
        }

        jQuery(document).on('shown.bs.dropdown', '.dataTables_wrapper .dropdown, .dataTables_wrapper .btn-group', function () {
            var dropdown = this;
            var menu = dropdown.querySelector('.dropdown-menu');
            var toggle = dropdown.querySelector('[data-toggle="dropdown"], [data-bs-toggle="dropdown"]');

            if (!menu || !toggle) return;

            var placeholder = document.createComment('datatable-dropdown-placeholder');
            menu.parentNode.insertBefore(placeholder, menu);

            activeDropdown = {
                dropdown: dropdown,
                menu: menu,
                toggle: toggle,
                placeholder: placeholder
            };

            document.body.appendChild(menu);
            menu.classList.add('dt-dropdown-portal');
            positionPortalDropdown();
        });

        jQuery(document).on('hide.bs.dropdown', function (event) {
            if (!activeDropdown || activeDropdown.dropdown !== event.target) return;

            var menu = activeDropdown.menu;
            var placeholder = activeDropdown.placeholder;

            menu.classList.remove('dt-dropdown-portal');
            menu.style.left = '';
            menu.style.top = '';

            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.insertBefore(menu, placeholder);
                placeholder.parentNode.removeChild(placeholder);
            }

            activeDropdown = null;
        });

        jQuery(window).on('scroll resize orientationchange', positionPortalDropdown);
        jQuery(document).on('scroll', '.dataTables_scrollBody', positionPortalDropdown);
    })();
</script>
