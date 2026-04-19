<script src="{{ mix('js/app.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/1.4.0/perfect-scrollbar.js"></script>
<script src="{{ asset('vendor/datatables/buttons.server-side.js') }}"></script>

@include('sweetalert::alert')

@yield('third_party_scripts')

@livewireScripts

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
