<?php

namespace Modules\Sale\DataTables;

use Carbon\Carbon;
use Modules\Sale\Entities\Sale;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SalesDataTable extends DataTable
{
    private function formatDateWithCreatedTime($row, string $field = 'date'): string
    {
        $date = $row->{$field} ?? null;
        $createdAt = $row->created_at ?? null;

        if (empty($date) && empty($createdAt)) {
            return '-';
        }

        if (empty($date) && !empty($createdAt)) {
            return Carbon::parse($createdAt)->format('d-m-Y H:i');
        }

        $datePart = Carbon::parse($date)->format('Y-m-d');
        $timePart = !empty($createdAt)
            ? Carbon::parse($createdAt)->format('H:i')
            : '00:00';

        return Carbon::parse($datePart . ' ' . $timePart)->format('d-m-Y H:i');
    }

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)

            // ✅ Row merah kalau soft deleted
            ->setRowClass(function ($row) {
                return !empty($row->deleted_at) ? 'table-danger' : '';
            })
            ->addRowAttr('data-href', function ($row) {
                return !empty($row->deleted_at) ? null : route('sales.show', $row->id);
            })

            // ✅ date tampil dengan jam dari created_at
            ->editColumn('date', function ($row) {
                return $this->formatDateWithCreatedTime($row, 'date');
            })

            ->addColumn('created_datetime', function ($row) {
                if (empty($row->created_at)) {
                    return '-';
                }

                return Carbon::parse($row->created_at)->format('d-m-Y H:i');
            })

            ->addColumn('deleted_datetime', function ($row) {
                if (empty($row->deleted_at)) {
                    return '-';
                }

                return Carbon::parse($row->deleted_at)->format('d-m-Y H:i');
            })

            /**
             * ✅ IMPORTANT:
             * - total_amount = Invoice Total (grand total after discount)
             * - paid_amount  = Cash Received (uang benar-benar diterima saat buat invoice)
             * - due_amount   = Amount to Receive / Balance (sisa tagihan setelah DP allocated + cash)
             */
            ->addColumn('total_amount', function ($row) {
                return format_currency((int) ($row->total_amount ?? 0));
            })
            ->addColumn('paid_amount', function ($row) {
                return format_currency((int) ($row->paid_amount ?? 0));
            })
            ->addColumn('due_amount', function ($row) {
                return format_currency((int) ($row->due_amount ?? 0));
            })

            ->addColumn('payment_status', function ($data) {
                return view('sale::partials.payment-status', compact('data'));
            })
            ->addColumn('delivery_status', function ($row) {
                return '<span class="badge ' . e($row->derived_delivery_status_class) . '">' . e($row->derived_delivery_status_label) . '</span>';
            })
            ->addColumn('source', function ($row) {
                return e($row->derived_source_label);
            })
            ->addColumn('action', function ($data) {
                return view('sale::partials.actions', compact('data'));
            })
            ->rawColumns(['delivery_status']);
    }

    public function query(Sale $model)
    {
        $deletedFilter = request('deleted_filter', 'all');

        $query = $model->newQuery()
            ->with(['saleDeliveries.saleOrder'])
            ->withCount(['salePayments', 'saleDeliveries']);

        if ($deletedFilter === 'all') {
            $query->withTrashed();
        } elseif ($deletedFilter === 'trashed') {
            $query->onlyTrashed();
        }

        return $query;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('sales-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom(
                "<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                "tr" .
                "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>"
            )
            ->orderBy(0)
            ->buttons(
                Button::make('excel')->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),
                Button::make('print')->text('<i class="bi bi-printer-fill"></i> Print'),
                Button::make('reset')->text('<i class="bi bi-x-circle"></i> Reset'),
                Button::make('reload')->text('<i class="bi bi-arrow-repeat"></i> Reload')
            );
    }

    protected function getColumns()
    {
        return [
            Column::make('date')
                ->title('Date Time')
                ->className('text-center align-middle'),

            Column::make('reference')
                ->className('text-center align-middle'),

            Column::make('customer_name')
                ->title('Customer')
                ->className('text-center align-middle'),

            Column::computed('total_amount')
                ->title('Invoice Total')
                ->className('text-center align-middle'),

            Column::computed('paid_amount')
                ->title('Cash Received')
                ->className('text-center align-middle'),

            Column::computed('due_amount')
                ->title('Amount to Receive')
                ->className('text-center align-middle'),

            Column::computed('payment_status')
                ->className('text-center align-middle'),

            Column::computed('delivery_status')
                ->title('Delivery Status')
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false),

            Column::computed('source')
                ->title('Source')
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false),

            Column::computed('created_datetime')
                ->title('Created At')
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false),

            Column::computed('deleted_datetime')
                ->title('Deleted At')
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')->visible(false),
            Column::make('deleted_at')->visible(false),
        ];
    }

    protected function filename()
    {
        return 'Sales_' . date('YmdHis');
    }
}
