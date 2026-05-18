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
       Keep one project-controlled indicator even when module views load the
       Bootstrap DataTables CSS, which also injects sorting pseudo-elements. */
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
        background-image: none !important;
    }

    table.dataTable thead th.sorting_disabled,
    table.dataTable thead th.dt-orderable-none,
    table.dataTable thead td.sorting_disabled,
    table.dataTable thead td.dt-orderable-none {
        cursor: default;
        padding-right: .75rem !important;
        background-image: none !important;
    }

    table.dataTable thead th.sorting::before,
    table.dataTable thead th.sorting_asc::before,
    table.dataTable thead th.sorting_desc::before,
    table.dataTable thead th.sorting_disabled::before,
    table.dataTable thead th.dt-orderable-asc::before,
    table.dataTable thead th.dt-orderable-desc::before,
    table.dataTable thead th.dt-ordering-asc::before,
    table.dataTable thead th.dt-ordering-desc::before,
    table.dataTable thead th.dt-orderable-none::before,
    table.dataTable thead td.sorting::before,
    table.dataTable thead td.sorting_asc::before,
    table.dataTable thead td.sorting_desc::before,
    table.dataTable thead td.sorting_disabled::before,
    table.dataTable thead td.dt-orderable-asc::before,
    table.dataTable thead td.dt-orderable-desc::before,
    table.dataTable thead td.dt-ordering-asc::before,
    table.dataTable thead td.dt-ordering-desc::before,
    table.dataTable thead td.dt-orderable-none::before,
    table.dataTable thead th .dt-column-order,
    table.dataTable thead td .dt-column-order {
        content: none !important;
        display: none !important;
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

    .dataTables_wrapper,
    .table-wrap {
        width: 100%;
    }

    .dataTables_scroll,
    .dataTables_scrollHead,
    .dataTables_scrollBody {
        width: 100% !important;
    }

    .dataTables_scrollBody {
        overflow-x: auto !important;
        overflow-y: hidden !important;
    }

    .dataTables_wrapper table.dataTable {
        margin-bottom: 0 !important;
        white-space: nowrap;
    }

    .dt-dropdown-portal {
        position: fixed !important;
        z-index: 2050 !important;
        margin: 0 !important;
        right: auto !important;
        bottom: auto !important;
        transform: none !important;
        display: block !important;
        max-width: calc(100vw - 16px);
        overscroll-behavior: contain;
        will-change: top, left;
    }

    .dt-dropdown-portal:not(.dt-dropdown-portal-ready) {
        visibility: hidden;
    }
</style>
