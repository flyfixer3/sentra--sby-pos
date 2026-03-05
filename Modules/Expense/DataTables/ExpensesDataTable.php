<?php

namespace Modules\Expense\DataTables;

use Carbon\Carbon;
use Modules\Expense\Entities\Expense;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ExpensesDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->editColumn('date', function ($row) {
                $d = $row->date;
                if (!$d) return '-';
                return Carbon::parse($d)->format('d-m-Y');
            })
            ->addColumn('type_badge', function ($row) {
                $type = strtolower((string) ($row->type ?? 'credit'));
                if ($type === 'debit') {
                    return '<span class="badge bg-success">DEBIT (IN)</span>';
                }
                return '<span class="badge bg-danger">CREDIT (OUT)</span>';
            })
            ->addColumn('cash_account', function ($row) {
                return $row->payment_method ? e($row->payment_method) : '-';
            })
            ->addColumn('debit_amount', function ($row) {
                $type = strtolower((string) ($row->type ?? 'credit'));
                return $type === 'debit' ? format_currency((int) $row->amount) : '-';
            })
            ->addColumn('credit_amount', function ($row) {
                $type = strtolower((string) ($row->type ?? 'credit'));
                return $type === 'credit' ? format_currency((int) $row->amount) : '-';
            })
            ->addColumn('action', function ($row) {
                $data = $row;
                return view('expense::expenses.partials.actions', compact('data'));
            })
            ->rawColumns(['type_badge', 'action']);
    }

    public function query(Expense $model)
    {
        $q = $model->newQuery()->with('category');

        // FILTERS dari UI (datatable ajax)
        $type = request('type');
        if ($type && in_array($type, ['debit', 'credit'], true)) {
            $q->where('type', $type);
        }

        $categoryId = request('category_id');
        if ($categoryId && is_numeric($categoryId)) {
            $q->where('category_id', (int) $categoryId);
        }

        $dateFrom = request('date_from');
        if ($dateFrom) {
            $q->whereDate('date', '>=', $dateFrom);
        }

        $dateTo = request('date_to');
        if ($dateTo) {
            $q->whereDate('date', '<=', $dateTo);
        }

        return $q->orderByDesc('date')->orderByDesc('id');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('expenses-table')
            ->columns($this->getColumns())
            ->ajax([
                'url'  => route('expenses.index'),
                'type' => 'GET',
                'data' => 'function(d){
                    d.type = $("#filter_type").val();
                    d.category_id = $("#filter_category").val();
                    d.date_from = $("#filter_date_from").val();
                    d.date_to = $("#filter_date_to").val();
                }',
            ])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                  "tr" .
                  "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
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
            Column::make('date')->title('Date')->className('text-center align-middle'),
            Column::make('reference')->title('Reference')->className('text-center align-middle'),
            Column::computed('type_badge')->title('Type')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::make('category.category_name')->title('Category')->className('text-center align-middle'),
            Column::computed('cash_account')->title('Cash/Bank')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::computed('debit_amount')->title('Debit')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::computed('credit_amount')->title('Credit')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::make('details')->title('Details')->className('text-center align-middle'),
            Column::computed('action')->exportable(false)->printable(false)->className('text-center align-middle'),
        ];
    }

    protected function filename()
    {
        return 'Expenses_' . date('YmdHis');
    }
}