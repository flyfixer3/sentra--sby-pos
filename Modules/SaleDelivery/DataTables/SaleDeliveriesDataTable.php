<?php

namespace Modules\SaleDelivery\DataTables;

use Carbon\Carbon;
use App\Support\BranchContext;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SaleDeliveriesDataTable extends DataTable
{
    private function formatDateWithCreatedTime($row, string $field = 'date'): string
    {
        $date = $row->{$field} ?? null;
        $createdAt = $row->created_at ?? null;

        if (empty($date) && empty($createdAt)) return '-';

        if (empty($date) && !empty($createdAt)) {
            return Carbon::parse($createdAt)->format('d-m-Y H:i');
        }

        $datePart = Carbon::parse($date)->format('Y-m-d');
        $timePart = !empty($createdAt) ? Carbon::parse($createdAt)->format('H:i') : '00:00';

        return Carbon::parse($datePart . ' ' . $timePart)->format('d-m-Y H:i');
    }

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addRowAttr('data-href', function ($row) {
                return route('sale-deliveries.show', $row->id);
            })

            ->editColumn('date', fn($row) => $this->formatDateWithCreatedTime($row, 'date'))

            ->editColumn('customer_name', fn($row) => $row->customer_name ?? '-')

            ->editColumn('items_count', fn($row) => (int) ($row->items_count ?? 0))

            ->editColumn('status', function ($row) {
                return view('saledelivery::partials.status', ['data' => $row]);
            })

            ->editColumn('created_by_name', fn($row) => $row->created_by_name ?? '-')
            ->editColumn('confirmed_by_name', fn($row) => $row->confirmed_by_name ?? '-')

            ->addColumn('action', function ($row) {
                return view('saledelivery::partials.actions', ['data' => $row]);
            });
    }

    public function query(SaleDelivery $model)
    {
        $branchId = BranchContext::id();

        $q = $branchId
            ? $model->query()
            : $model->newQuery()->withoutGlobalScopes();

        return $q->select([
                'sale_deliveries.*',
                'customers.customer_name as customer_name',
                'u_created.name as created_by_name',
                'u_confirmed.name as confirmed_by_name',
            ])
            ->leftJoin('customers', 'customers.id', '=', 'sale_deliveries.customer_id')
            ->leftJoin('users as u_created', 'u_created.id', '=', 'sale_deliveries.created_by')
            ->leftJoin('users as u_confirmed', 'u_confirmed.id', '=', 'sale_deliveries.confirmed_by')
            ->withCount('items');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('sale-deliveries-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom(
                "<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                "tr" .
                "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>"
            )
            // ✅ FIX: index created_at sekarang di kolom ke-8 (bukan 9)
            ->orderBy(8)
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

            Column::make('customer_name')
                ->name('customers.customer_name')
                ->title('Customer')
                ->className('text-center align-middle'),

            Column::make('items_count')
                ->title('Items')
                ->className('text-center align-middle'),

            Column::make('status')
                ->className('text-center align-middle'),

            Column::make('created_by_name')
                ->name('u_created.name')
                ->title('Created By')
                ->className('text-center align-middle'),

            Column::make('confirmed_by_name')
                ->name('u_confirmed.name')
                ->title('Confirmed By')
                ->className('text-center align-middle'),

            Column::computed('action')
                ->orderable(false)
                ->searchable(false)
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')->visible(false),
        ];
    }

    protected function filename()
    {
        return 'SaleDeliveries_' . date('YmdHis');
    }
}
