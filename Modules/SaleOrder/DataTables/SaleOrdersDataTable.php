<?php

namespace Modules\SaleOrder\DataTables;

use Carbon\Carbon;
use Modules\SaleOrder\Entities\SaleOrder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SaleOrdersDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('customer', fn($row) => e($row->customer?->customer_name ?? '-'))
            ->editColumn('date', function ($row) {
                if (empty($row->date)) return '-';
                return Carbon::parse($row->date)->format('d-m-Y');
            })
            ->editColumn('status', function ($row) {
                $s = strtolower((string) ($row->status ?? 'pending'));
                $badge = 'secondary';
                if ($s === 'pending') $badge = 'warning';
                if ($s === 'partial_delivered') $badge = 'info';
                if ($s === 'delivered') $badge = 'success';
                if ($s === 'cancelled') $badge = 'danger';
                return '<span class="badge badge-' . $badge . '">' . strtoupper(e($s)) . '</span>';
            })
            ->addColumn('action', function ($row) {
                $showUrl = route('sale-orders.show', $row->id);
                return '<a class="btn btn-sm btn-primary" href="' . $showUrl . '"><i class="bi bi-eye"></i> View</a>';
            })
            ->rawColumns(['status', 'action']);
    }

    public function query(SaleOrder $model)
    {
        return $model->query()->with(['customer']);
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('sale-orders-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                  "tr" .
                  "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(0)
            ->buttons(
                Button::make('export'),
                Button::make('print'),
                Button::make('reset'),
                Button::make('reload')
            );
    }

    protected function getColumns()
    {
        return [
            Column::make('id')->title('#'),
            Column::make('reference')->title('Reference'),
            Column::make('date')->title('Date'),
            Column::computed('customer')->title('Customer')->orderable(false)->searchable(false),
            Column::make('status')->title('Status'),
            Column::computed('action')->exportable(false)->printable(false)->width(100)->addClass('text-center'),
        ];
    }

    protected function filename()
    {
        return 'SaleOrders_' . date('YmdHis');
    }
}
