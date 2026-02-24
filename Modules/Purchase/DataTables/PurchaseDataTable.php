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

            // ✅ (REMOVED) kolom Transaction Date (purchases.date)
            // ->editColumn('date', function ($data) {
            //     if (empty($data->date)) return '-';
            //     return Carbon::parse($data->date)->format('d-m-Y');
            // })

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

            ->editColumn('payment_status', function ($data) {
                return view('purchase::partials.payment-status', compact('data'));
            })

            // ✅ Created At (DateTime) -> ambil dari created_at (timestamp)
            ->addColumn('created_datetime', function ($data) {
                if (empty($data->created_at)) return '-';
                return Carbon::parse($data->created_at)->format('d-m-Y H:i');
            })

            // ✅ Created By (nama user)
            ->addColumn('created_by_name', function ($data) {
                return $data->created_by_name ?? '-';
            })

            // ✅ Last Updated By (nama user)
            ->addColumn('updated_by_name', function ($data) {
                return $data->updated_by_name ?? '-';
            })

            ->addColumn('action', function ($data) {
                return view('purchase::partials.actions', compact('data'));
            });
    }

    public function query(Purchase $model)
    {
        return $model->newQuery()
            ->withTrashed()
            ->select([
                'purchases.*',
                'suppliers.supplier_name as supplier_name',

                'u_created.name as created_by_name',
                'u_updated.name as updated_by_name',
            ])
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->leftJoin('users as u_created', 'u_created.id', '=', 'purchases.created_by')
            ->leftJoin('users as u_updated', 'u_updated.id', '=', 'purchases.updated_by');
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

            /**
             * ✅ URUTAN KOLOM di getColumns() sekarang:
             * 0 reference
             * 1 reference_supplier
             * 2 supplier_name
             * 3 total_amount
             * 4 due_amount
             * 5 due_date
             * 6 payment_status
             * 7 deleted_at (hidden)
             * 8 created_datetime
             * 9 created_by_name
             * 10 updated_by_name
             * 11 action
             * 12 created_at (hidden)  <-- ini yang dipakai sorting
             */
            ->orderBy(12, 'desc')

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

            // ✅ (REMOVED) kolom date karena redundant dengan Created At
            // Column::make('date')
            //     ->title('Date')
            //     ->className('text-center align-middle'),

            Column::make('reference_supplier')
                ->title('Invoice')
                ->className('text-center align-middle'),

            Column::make('supplier_name')
                ->title('Supplier')
                ->className('text-center align-middle'),

            Column::computed('total_amount')
                ->className('text-center align-middle'),

            Column::computed('due_amount')
                ->className('text-center align-middle'),

            Column::make('due_date')
                ->className('text-center align-middle'),

            Column::make('payment_status')
                ->className('text-center align-middle'),

            // hidden - tetap ada kalau kamu butuh logic soft delete
            Column::make('deleted_at')->visible(false),

            // ✅ Created At (datetime)
            Column::computed('created_datetime')
                ->title('Created At')
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false),

            Column::computed('created_by_name')
                ->title('Created By')
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false),

            Column::computed('updated_by_name')
                ->title('Last Updated By')
                ->className('text-center align-middle')
                ->orderable(false)
                ->searchable(false),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            // ✅ hidden created_at untuk sorting (jangan dihapus)
            Column::make('created_at')->visible(false),
        ];
    }

    protected function filename()
    {
        return 'Purchase_' . date('YmdHis');
    }
}