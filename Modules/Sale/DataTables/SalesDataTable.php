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

            // ✅ date tampil dengan jam dari created_at
            ->editColumn('date', function ($row) {
                return $this->formatDateWithCreatedTime($row, 'date');
            })

            ->addColumn('total_amount', function ($data) {
                return format_currency($data->total_amount);
            })
            ->addColumn('paid_amount', function ($data) {
                return format_currency($data->paid_amount);
            })
            ->addColumn('due_amount', function ($data) {
                return format_currency($data->due_amount);
            })
            ->addColumn('payment_status', function ($data) {
                return view('sale::partials.payment-status', compact('data'));
            })
            ->addColumn('action', function ($data) {
                return view('sale::partials.actions', compact('data'));
            });
    }

    public function query(Sale $model)
    {
        return $model->newQuery();
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
                ->className('text-center align-middle'),

            Column::computed('paid_amount')
                ->className('text-center align-middle'),

            Column::computed('due_amount')
                ->className('text-center align-middle'),

            Column::computed('payment_status')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')->visible(false),
        ];
    }

    protected function filename()
    {
        return 'Sales_' . date('YmdHis');
    }
}
