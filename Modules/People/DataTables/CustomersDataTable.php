<?php

namespace Modules\People\DataTables;


use Modules\People\Entities\Customer;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class CustomersDataTable extends DataTable
{

    public function dataTable($query) {
        return datatables()
            ->eloquent($query)
            ->addRowAttr('data-href', function ($data) {
                return route('customers.show', $data->id);
            })
            ->editColumn('created_at', function ($data) {
                return $data->created_at ? $data->created_at->format('d-m-Y H:i') : '-';
            })
            ->addColumn('action', function ($data) {
                return view('people::customers.partials.actions', compact('data'));
            });
    }

    public function query(Customer $model) {
        $query = $model->newQuery();

        $active = session('active_branch');
        if (is_numeric($active)) {
            $query->where(function ($q) use ($active) {
                $q->whereNull('branch_id')
                    ->orWhere('branch_id', (int) $active);
            });
        }

        return $query;
    }

    public function html() {
        return $this->builder()
            ->setTableId('customers-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                       'tr' .
                                 <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(3, 'desc')
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
            Column::make('customer_name')
                ->className('text-center align-middle'),

            Column::make('customer_email')
                ->className('text-center align-middle'),

            Column::make('customer_phone')
                ->className('text-center align-middle'),

            Column::make('created_at')
                ->title('Created At')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),
        ];
    }

    protected function filename() {
        return 'Customers_' . date('YmdHis');
    }
}
