<?php

namespace Modules\Adjustment\DataTables;

use Carbon\Carbon;
use Modules\Adjustment\Entities\Adjustment;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AdjustmentsDataTable extends DataTable
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

            /**
             * ✅ FIX: field hasil withSum adalah adjusted_products_sum_quantity
             * BUKAN adjusted_products_quantity_sum
             */
            ->editColumn('adjusted_products_sum_quantity', function ($row) {
                $v = $row->adjusted_products_sum_quantity ?? 0;
                // kadang driver DB ngasih string/decimal, kita paksa int
                return (int) $v;
            })

            ->addColumn('action', function ($data) {
                return view('adjustment::partials.actions', compact('data'));
            });
    }

    public function query(Adjustment $model)
    {
        /**
         * adjusted_products_count        = jumlah baris/line item di adjusted_products
         * adjusted_products_sum_quantity = total pcs (SUM adjusted_products.quantity)
         */
        return $model->query()
            ->withCount('adjustedProducts')
            ->withSum('adjustedProducts', 'quantity');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('adjustments-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom(
                "<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                "tr" .
                "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>"
            )
            // ✅ kolom created_at hidden ada di index terakhir (lihat getColumns)
            ->orderBy(5)
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

            // ✅ GANTI TITLE SAJA
            Column::make('adjusted_products_count')
                ->title('Product Variations')
                ->className('text-center align-middle'),

            Column::make('adjusted_products_sum_quantity')
                ->title('Total Qty')
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
        return 'Adjustments_' . date('YmdHis');
    }
}