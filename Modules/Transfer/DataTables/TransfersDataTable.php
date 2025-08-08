<?php

namespace Modules\Transfer\DataTables;

use Modules\Transfer\Entities\TransferRequest;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class TransfersDataTable extends DataTable
{
    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addColumn('fromWarehouse', function ($data) {
                return $data->fromWarehouse->warehouse_name ?? '-';
            })
            ->addColumn('toBranch', function ($data) {
                return $data->toBranch->name ?? '-';
            })
            ->addColumn('print_count', function ($data) {
                if ($data->printLogs->count() > 0) {
                    return '<span class="badge bg-info">' . $data->printLogs->count() . 'x</span>';
                } else {
                    return '<span class="text-muted">Belum</span>';
                }
            })
            ->addColumn('action', function ($data) {
                return view('transfer::partials.actions', compact('data'));
            })
            ->editColumn('created_at', function ($data) {
                $formatedDate = Carbon::createFromFormat('Y-m-d H:i:s', $data->created_at)
                    ->format('d-m-Y H:i:s');
                return $formatedDate;
            })
            ->rawColumns(['print_count', 'action']);
    }

    public function query(TransferRequest $model) {
        return $model->newQuery()->with([
            'fromWarehouse',
            'toBranch',
            'toWarehouse',
            'printLogs', // penting agar count() tidak query ulang
        ]);
    }

    public function html() {
        return $this->builder()
            ->setTableId('transfer-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                    'tr' .
                    <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(0)
            ->buttons(
                Button::make('excel')->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),
                Button::make('print')->text('<i class="bi bi-printer-fill"></i> Print'),
                Button::make('reset')->text('<i class="bi bi-x-circle"></i> Reset'),
                Button::make('reload')->text('<i class="bi bi-arrow-repeat"></i> Reload')
            );
    }

    protected function getColumns() {
        return [
            Column::make('reference')
                ->className('text-center align-middle'),

            Column::make('fromWarehouse')
                ->title('From Warehouse')
                ->className('text-center align-middle'),

            Column::make('toBranch')
                ->title('To Branch')
                ->className('text-center align-middle'),

            Column::make('note')
                ->className('text-center align-middle'),

            Column::make('created_at')
                ->title('Created At')
                ->className('text-center align-middle'),

            Column::make('print_count')
                ->title('Cetak')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),
        ];
    }

    protected function filename() {
        return 'Transfers_' . date('YmdHis');
    }
}
