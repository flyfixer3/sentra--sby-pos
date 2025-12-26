<?php

namespace Modules\Mutation\DataTables;

use Modules\Mutation\Entities\Mutation;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class MutationsDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            // ✅ bikin kolom aman walau relasi null
            ->addColumn('warehouse_name', function ($row) {
                return optional($row->warehouse)->warehouse_name ?? '-';
            })
            ->addColumn('product_code', function ($row) {
                return optional($row->product)->product_code ?? '-';
            })
            ->addColumn('action', function ($data) {
                return view('mutation::partials.actions', compact('data'));
            })
            ->editColumn('created_at', function ($row) {
                return Carbon::parse($row->created_at)->format('d-m-Y H:i:s');
            });
    }

    public function query(Mutation $model)
    {
        // ✅ eager load tetap di sini
        return $model->newQuery()->with(['product', 'warehouse']);
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('mutation-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                  'tr' .
                  <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(2)
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
            // ✅ ganti dari warehouse.warehouse_name -> warehouse_name
            Column::make('warehouse_name')
                ->title('Warehouse')
                ->className('text-center align-middle'),

            // ✅ ganti dari product.product_code -> product_code
            Column::make('product_code')
                ->title('Product Code')
                ->className('text-center align-middle'),

            Column::make('mutation_type')->className('text-center align-middle'),
            Column::make('reference')->className('text-center align-middle'),
            Column::make('note')->className('text-center align-middle'),
            Column::make('stock_early')->className('text-center align-middle'),
            Column::make('stock_in')->className('text-center align-middle'),
            Column::make('stock_out')->className('text-center align-middle'),
            Column::make('stock_last')->className('text-center align-middle'),

            Column::make('created_at')->visible(true),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),
        ];
    }

    protected function filename()
    {
        return 'Mutations_' . date('YmdHis');
    }
}
