<?php

namespace Modules\PurchaseDelivery\DataTables;

use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class PurchaseDeliveriesDataTable extends DataTable
{

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('purchase_order', function ($data) {
                return $data->purchaseOrder->reference;
            })
            ->addColumn('status', function ($data) {
                return view('purchase-deliveries::partials.status', compact('data'));
            })
            ->addColumn('action', function ($data) {
                return view('purchase-deliveries::partials.actions', compact('data'));
            });
    }

    public function query(PurchaseDelivery $model) {
        return $model->newQuery();
    }

    public function html() {
        return $this->builder()
            ->setTableId('purchase-deliveries-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(3)
            ->buttons(
                Button::make('excel')
                    ->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),
                Button::make('print')
                    ->text('<i class="bi bi-printer-fill"></i> Print'),
                Button::make('reset')
                    ->text('<i class="bi bi-x-circle"></i> Reset'),
                Button::make('reload')
                    ->text('<i class="bi bi-arrow-repeat"></i> Reload')
            );
    }

    protected function getColumns() {
        return [
            Column::computed('purchase_order')
                ->className('text-center align-middle'),
            Column::make('date')
                ->className('text-center align-middle'),

            Column::make('purchase_order_id')
                ->className('text-center align-middle'),

            Column::computed('status')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),
      
            Column::make('created_at')
                ->visible(false)
        ];
    }

    protected function filename() {
        return 'PurchaseDeliveries_' . date('YmdHis');
    }
}
