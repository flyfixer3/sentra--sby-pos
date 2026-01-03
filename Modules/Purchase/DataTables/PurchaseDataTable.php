<?php

namespace Modules\Purchase\DataTables;

use Carbon\Carbon;
use Modules\Purchase\Entities\Purchase;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PurchaseDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('total_amount', function ($data) {
                return format_currency($data->total_amount);
            })
            ->editColumn('due_date', function ($data) {

                // kalau sudah soft deleted → biar jelas
                if (!empty($data->deleted_at)) {
                    return '-';
                }

                // kalau due_date kosong / 0 → tampilkan "-"
                $days = (int) ($data->due_date ?? 0);
                if ($days <= 0) return '-';

                $baseDate = $data->date ? Carbon::parse($data->date) : Carbon::now();
                $target = $baseDate->copy()->addDays($days);

                $diff = Carbon::now()->diffInDays($target, false); // bisa minus

                // Kalau sudah Paid, tampil "Paid"
                if (($data->payment_status ?? null) === 'Paid') {
                    return 'Paid';
                }

                return $diff . ' Days';
            })
            ->addColumn('due_amount', function ($data) {
                return format_currency($data->due_amount);
            })
            ->editColumn('status', function ($data) {
                return view('purchase::partials.status', compact('data'));
            })
            ->editColumn('payment_status', function ($data) {
                return view('purchase::partials.payment-status', compact('data'));
            })
            ->addColumn('action', function ($data) {
                return view('purchase::partials.actions', compact('data'));
            });
    }

    public function query(Purchase $model)
    {
        $q = $model->newQuery()
            ->withTrashed() // ✅ include soft deleted
            ->select([
                'purchases.*',
                'suppliers.supplier_name as supplier_name',
            ])
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id');

        return $q;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('purchases-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom(
                "<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                "<'row'<'col-md-12'tr>>" .
                "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>"
            )
            ->orderBy(10) // created_at hidden column index (cek index kolom)
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
            Column::make('reference')
                ->className('text-center align-middle'),

            Column::make('reference_supplier')
                ->title('Invoice')
                ->className('text-center align-middle'),

            Column::make('supplier_name')
                ->title('Supplier')
                ->className('text-center align-middle'),

            Column::make('status')
                ->className('text-center align-middle'),

            Column::computed('total_amount')
                ->className('text-center align-middle'),

            Column::computed('due_amount')
                ->className('text-center align-middle'),

            Column::make('due_date')
                ->className('text-center align-middle'),

            Column::make('payment_status')
                ->className('text-center align-middle'),

            // ✅ kolom deleted_at (hidden) buat sorting/logic kalau kamu mau
            Column::make('deleted_at')->visible(false),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')->visible(false),
        ];
    }

    protected function filename()
    {
        return 'Purchase_' . date('YmdHis');
    }
}
