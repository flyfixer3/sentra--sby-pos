<!-- Dropezone CSS -->
<link rel="stylesheet" href="{{ asset('css/dropzone.css') }}">
<!-- CoreUI CSS -->
<link rel="stylesheet" href="{{ mix('css/app.css') }}" crossorigin="anonymous">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">

@yield('third_party_stylesheets')

@livewireStyles

@stack('page_css')

<style>
    div.dataTables_wrapper div.dataTables_length select {
        width: 65px;
        display: inline-block;
    }

    table tbody tr[data-href] {
        cursor: pointer;
    }

    table tbody tr[data-href]:hover {
        background-color: rgba(0, 0, 0, .035);
    }

    /* DataTables sorting affordance.
       The project loads DataTables JS globally, but not the Bootstrap DataTables CSS,
       so sortable headers need a small shared visual treatment. */
    table.dataTable thead th.sorting,
    table.dataTable thead th.sorting_asc,
    table.dataTable thead th.sorting_desc,
    table.dataTable thead th.dt-orderable-asc,
    table.dataTable thead th.dt-orderable-desc,
    table.dataTable thead td.sorting,
    table.dataTable thead td.sorting_asc,
    table.dataTable thead td.sorting_desc,
    table.dataTable thead td.dt-orderable-asc,
    table.dataTable thead td.dt-orderable-desc {
        cursor: pointer;
        position: relative;
        padding-right: 1.75rem !important;
        user-select: none;
    }

    table.dataTable thead th.sorting_disabled,
    table.dataTable thead th.dt-orderable-none,
    table.dataTable thead td.sorting_disabled,
    table.dataTable thead td.dt-orderable-none {
        cursor: default;
        padding-right: .75rem !important;
    }

    table.dataTable thead th.sorting::after,
    table.dataTable thead th.dt-orderable-asc.dt-orderable-desc::after,
    table.dataTable thead td.sorting::after,
    table.dataTable thead td.dt-orderable-asc.dt-orderable-desc::after {
        content: "\2195";
        position: absolute;
        right: .65rem;
        top: 50%;
        transform: translateY(-50%);
        color: #8a93a2;
        font-size: .75rem;
        line-height: 1;
        opacity: .75;
    }

    table.dataTable thead th.sorting_asc::after,
    table.dataTable thead th.dt-ordering-asc::after,
    table.dataTable thead td.sorting_asc::after,
    table.dataTable thead td.dt-ordering-asc::after {
        content: "\25B2";
        position: absolute;
        right: .65rem;
        top: 50%;
        transform: translateY(-50%);
        color: #321fdb;
        font-size: .72rem;
        line-height: 1;
        opacity: 1;
    }

    table.dataTable thead th.sorting_desc::after,
    table.dataTable thead th.dt-ordering-desc::after,
    table.dataTable thead td.sorting_desc::after,
    table.dataTable thead td.dt-ordering-desc::after {
        content: "\25BC";
        position: absolute;
        right: .65rem;
        top: 50%;
        transform: translateY(-50%);
        color: #321fdb;
        font-size: .72rem;
        line-height: 1;
        opacity: 1;
    }

    table.dataTable thead th.sorting:hover,
    table.dataTable thead th.sorting_asc:hover,
    table.dataTable thead th.sorting_desc:hover,
    table.dataTable thead th.dt-orderable-asc:hover,
    table.dataTable thead th.dt-orderable-desc:hover,
    table.dataTable thead td.sorting:hover,
    table.dataTable thead td.sorting_asc:hover,
    table.dataTable thead td.sorting_desc:hover,
    table.dataTable thead td.dt-orderable-asc:hover,
    table.dataTable thead td.dt-orderable-desc:hover {
        background-color: rgba(50, 31, 219, .04);
    }

    table.dataTable thead th.sorting_disabled::after,
    table.dataTable thead th.dt-orderable-none::after,
    table.dataTable thead td.sorting_disabled::after,
    table.dataTable thead td.dt-orderable-none::after {
        content: none !important;
    }
</style>
