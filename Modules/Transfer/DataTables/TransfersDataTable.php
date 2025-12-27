<?php

namespace Modules\Transfer\DataTables;

use Modules\Transfer\Entities\TransferRequest;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class TransfersDataTable extends DataTable
{
    private function activeBranch()
    {
        return session('active_branch');
    }

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('fromWarehouse', function ($row) {
                return optional($row->fromWarehouse)->warehouse_name ?? '-';
            })
            ->addColumn('toBranch', function ($row) {
                return optional($row->toBranch)->name ?? '-';
            })
            ->addColumn('status_badge', function ($row) {
                $status = strtolower((string) ($row->status ?? 'pending'));
                $map = [
                    'pending'   => 'warning text-dark',
                    'shipped'   => 'info',
                    'confirmed' => 'success',
                    'cancelled' => 'danger',
                ];
                $cls = $map[$status] ?? 'secondary';
                return '<span class="badge bg-'.$cls.'">'.strtoupper($status).'</span>';
            })
            ->addColumn('print_count', function ($row) {
                $count = $row->printLogs ? $row->printLogs->count() : 0;
                if ($count > 0) {
                    return '<span class="badge bg-info">'.$count.'x</span>';
                }
                return '<span class="text-muted">Belum</span>';
            })
            ->addColumn('action', function ($row) {
                return view('transfer::partials.actions', ['data' => $row]);
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y H:i:s') : '-';
            })
            ->rawColumns(['status_badge', 'print_count', 'action']);
    }

    public function query(TransferRequest $model)
    {
        $active = $this->activeBranch();

        // Base query
        $q = $model->newQuery()->with([
            'fromWarehouse',
            'toBranch',
            'toWarehouse',
            'printLogs',
        ]);

        /**
         * PENTING:
         * Kalau active_branch = 'all', jangan pakai global scope branch.
         * Karena global scope kamu kemungkinan akan where branch_id = 'all' (string) => SQL error.
         */
        if ($active === 'all') {
            $q = $model->newQuery()->withoutGlobalScopes()->with([
                'fromWarehouse',
                'toBranch',
                'toWarehouse',
                'printLogs',
            ]);
        } else {
            // kalau numeric branch id, aman (biarin global scope atau filter tambahan)
            // optional: kalau kamu butuh lebih ketat, bisa pakai where('branch_id', (int)$active)
        }

        // Filter status dari DataTables (yang kamu inject pakai JS)
        if (request()->filled('status')) {
            $q->where('status', request('status'));
        }

        return $q;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('transfer-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                    'tr' .
                    <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(4, 'desc')
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
            Column::make('reference')->title('Reference')->className('text-center align-middle'),
            Column::make('fromWarehouse')->title('From Warehouse')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::make('toBranch')->title('To Branch')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::make('note')->title('Note')->className('text-center align-middle'),
            Column::computed('status_badge')->title('Status')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::make('created_at')->title('Created At')->className('text-center align-middle'),
            Column::make('print_count')->title('Cetak')->orderable(false)->searchable(false)->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle')
                ->width(160),
        ];
    }

    protected function filename()
    {
        return 'Transfers_' . date('YmdHis');
    }
}
