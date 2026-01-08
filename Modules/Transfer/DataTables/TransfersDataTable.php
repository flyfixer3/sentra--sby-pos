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
                // ✅ ambil raw status dari DB (anti ketipu accessor/cast)
                $raw = $row->getRawOriginal('status');
                $status = strtolower(trim((string) ($raw ?? $row->status ?? 'pending')));

                $map = [
                    'pending'   => ['warning', 'text-dark'],
                    'shipped'   => ['info', ''],
                    'confirmed' => ['success', ''],
                    'issue'     => ['warning', 'text-dark'],
                    'cancelled' => ['danger', ''],
                ];

                [$bg, $extra] = $map[$status] ?? ['secondary', ''];
                $label = strtoupper($status);

                // ✅ ISSUE badge logic:
                // issue hanya relevan kalau sudah confirmed
                $issueCount = (int) ($row->issues_count ?? 0);

                if ($status === 'confirmed' && $issueCount > 0) {
                    // tampilkan confirmed + issue
                    // contoh: CONFIRMED • ISSUE (2)
                    return
                        '<span class="badge bg-success">CONFIRMED</span> ' .
                        '<span class="badge bg-warning text-dark ms-1">ISSUE (' . $issueCount . ')</span>';
                }

                return '<span class="badge bg-' . $bg . ' ' . $extra . '">' . $label . '</span>';
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
        $q = $model->newQuery();

        // Kalau active_branch = all => lepas global scope
        if ($active === 'all') {
            $q = $model->newQuery()->withoutGlobalScopes();
        }

        // Relasi yg kamu sudah pakai
        $q->with([
            'fromWarehouse',
            'toBranch',
            'toWarehouse',
            'printLogs',
        ]);

        /**
         * ✅ issues_count = SUM(quantity) bukan COUNT(row)
         * - defect_sum = SUM(product_defect_items.quantity)
         * - damaged_sum = SUM(product_damaged_items.quantity WHERE damage_type=damaged)
         * - missing_sum = SUM(product_damaged_items.quantity WHERE damage_type=missing)
         */
        $q->select('transfer_requests.*')
            ->selectSub(function ($sub) {
                $sub->from('product_defect_items')
                    ->selectRaw('COALESCE(SUM(quantity),0)')
                    ->whereColumn('product_defect_items.reference_id', 'transfer_requests.id')
                    ->where('product_defect_items.reference_type', TransferRequest::class);
            }, 'defect_sum')
            ->selectSub(function ($sub) {
                $sub->from('product_damaged_items')
                    ->selectRaw('COALESCE(SUM(quantity),0)')
                    ->whereColumn('product_damaged_items.reference_id', 'transfer_requests.id')
                    ->where('product_damaged_items.reference_type', TransferRequest::class)
                    ->where('product_damaged_items.damage_type', 'damaged');
            }, 'damaged_sum')
            ->selectSub(function ($sub) {
                $sub->from('product_damaged_items')
                    ->selectRaw('COALESCE(SUM(quantity),0)')
                    ->whereColumn('product_damaged_items.reference_id', 'transfer_requests.id')
                    ->where('product_damaged_items.reference_type', TransferRequest::class)
                    ->where('product_damaged_items.damage_type', 'missing');
            }, 'missing_sum')
            ->selectRaw('(COALESCE(defect_sum,0) + COALESCE(damaged_sum,0) + COALESCE(missing_sum,0)) as issues_count');

        // Filter status dari DataTables
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
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                  "tr" .
                  "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(5, 'desc')
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
