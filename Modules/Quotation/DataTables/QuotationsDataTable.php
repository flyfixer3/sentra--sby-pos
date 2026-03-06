<?php

namespace Modules\Quotation\DataTables;

use Modules\Quotation\Entities\Quotation;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class QuotationsDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->editColumn('date', function ($data) {
                $dateText = '-';
                $timeText = '';

                if (!empty($data->getRawOriginal('date'))) {
                    $dateText = \Carbon\Carbon::parse($data->getRawOriginal('date'))->format('d M, Y');
                }

                if (!empty($data->created_at)) {
                    $timeText = \Carbon\Carbon::parse($data->created_at)->format('H:i');
                }

                if ($timeText !== '') {
                    return $dateText . ' ' . $timeText;
                }

                return $dateText;
            })
            ->addColumn('total_amount', function ($data) {
                return format_currency($data->total_amount);
            })
            ->addColumn('status', function ($data) {
                return view('quotation::partials.status', compact('data'));
            })
            ->addColumn('action', function ($data) {
                return view('quotation::partials.actions', compact('data'));
            })
            ->rawColumns(['date', 'status', 'action']);
    }

    public function query(Quotation $model)
    {
        return $model->newQuery();
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('quotations-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(6)
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
            Column::make('date')->className('text-center align-middle'),
            Column::make('reference')->className('text-center align-middle'),
            Column::make('customer_name')->title('Customer')->className('text-center align-middle'),
            Column::computed('status')->className('text-center align-middle'),
            Column::computed('total_amount')->className('text-center align-middle'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),
            Column::make('created_at')->visible(false),
        ];
    }

    protected function filename()
    {
        return 'Quotations_' . date('YmdHis');
    }
}
