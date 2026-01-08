<?php

namespace Modules\PurchaseDelivery\DataTables;

use Carbon\Carbon;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class PurchaseDeliveriesDataTable extends DataTable
{
    private function formatDateWithCreatedTime($row, string $field = 'date'): string
    {
        $date = $row->{$field} ?? null;
        $createdAt = $row->created_at ?? null;

        if (empty($date) && empty($createdAt)) {
            return '-';
        }

        // Kalau date kosong, fallback ke created_at full
        if (empty($date) && !empty($createdAt)) {
            return Carbon::parse($createdAt)->format('d-m-Y H:i');
        }

        // date ada, tapi tidak ada jam -> ambil jam dari created_at
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

            ->addColumn('purchase_order', function ($data) {
                // dari PO: ambil reference PO, kalau walk-in: WALK-IN
                if (!empty($data->po_reference)) return $data->po_reference;
                return 'WALK-IN';
            })

            ->addColumn('supplier', function ($data) {
                // prioritas: supplier PO, kalau null => supplier dari purchase (walk-in)
                if (!empty($data->po_supplier_name)) return $data->po_supplier_name;
                if (!empty($data->walkin_supplier_name)) return $data->walkin_supplier_name;
                return '-';
            })

            ->addColumn('warehouse', function ($data) {
                return $data->warehouse_name ?? '-';
            })

            ->addColumn('status', function ($data) {
                return view('purchase-deliveries::partials.status', compact('data'));
            })

            ->addColumn('action', function ($data) {
                return view('purchase-deliveries::partials.actions', compact('data'));
            });
    }

    public function query(PurchaseDelivery $model)
    {
        return $model->newQuery()
            ->select([
                'purchase_deliveries.*',
                'purchase_orders.reference as po_reference',
                'suppliers.supplier_name as po_supplier_name',
                'purchases.supplier_name as walkin_supplier_name',
                'warehouses.warehouse_name as warehouse_name',
            ])
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'purchase_deliveries.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            // ✅ ini kunci walk-in: purchases.purchase_delivery_id -> purchase_deliveries.id
            ->leftJoin('purchases', 'purchases.purchase_delivery_id', '=', 'purchase_deliveries.id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'purchase_deliveries.warehouse_id');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('purchase-deliveries-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom(
                "<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                "tr" .
                "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>"
            )
            ->orderBy(1)
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
            Column::computed('purchase_order')
                ->title('PO No.')
                ->className('text-center align-middle'),

            Column::make('date')
                ->title('Date Time')
                ->className('text-center align-middle'),

            Column::computed('supplier')
                ->title('Supplier')
                ->className('text-center align-middle'),

            Column::computed('warehouse')
                ->title('Warehouse')
                ->className('text-center align-middle'),

            Column::computed('status')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),
        ];
    }

    protected function filename()
    {
        return 'PurchaseDeliveries_' . date('YmdHis');
    }
}
