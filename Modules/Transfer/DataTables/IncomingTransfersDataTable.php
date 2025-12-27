<?php

namespace Modules\Transfer\DataTables;

use Carbon\Carbon;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Modules\Transfer\Entities\TransferRequest as Transfer;

class IncomingTransfersDataTable extends DataTable
{
    protected function activeBranchId(): ?int
    {
        return session('active_branch') ?? (auth()->user()->default_branch_id ?? null);
    }

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->with(['fromWarehouse', 'toBranch'])
            ->addColumn('from_warehouse', function ($row) {
                return optional($row->fromWarehouse)->warehouse_name ?? '-';
            })
            ->addColumn('to_branch', fn ($row) => optional($row->toBranch)->name ?? '-')
            ->addColumn('status_badge', function ($row) {
                $status = $row->status ?? 'pending';
                $map = ['pending'=>'secondary','shipped'=>'info','confirmed'=>'success','cancelled'=>'danger'];
                return '<span class="badge bg-'.($map[$status] ?? 'secondary').'">'.strtoupper($status).'</span>';
            })
            ->addColumn('cetak', function ($row) {
                if (isset($row->print_count)) return $row->print_count > 0 ? 'Sudah' : 'Belum';
                if (isset($row->is_printed))  return $row->is_printed ? 'Sudah' : 'Belum';
                if (isset($row->printed_at))  return $row->printed_at ? 'Sudah' : 'Belum';
                return 'Belum';
            })
            ->addColumn('action', fn ($row) => view('transfer::partials.actions-incoming', compact('row')))
            ->editColumn('created_at', fn ($row) => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y H:i:s') : '-')
            ->rawColumns(['action','status_badge']);
    }

    /** Incoming = menuju cabang aktif (bypass scope cabang pengirim). */
    public function query(Transfer $model)
    {
        $active = session('active_branch'); // 'all' / int
        $table  = $model->getTable();

        $q = $model->newQuery()
            ->withoutGlobalScopes()
            ->with(['fromWarehouse','toBranch'])
            ->orderByDesc($table.'.created_at');

        if ($active !== 'all') {
            $q->where($table.'.to_branch_id', (int)$active);
        }

        $status = request('status');
        if ($status && $status !== 'all') {
            $q->where($table.'.status', $status);
        }

        return $q;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('incoming-transfers-table')
            ->columns($this->getColumns())
            ->ajax([
                'url'  => route('transfers.datatable.incoming'),
                'type' => 'GET',
                'data' => 'function(d){ d.status = $("#filter_status_incoming").val(); }',
            ])
            ->dom('Bfrtip')
            ->orderBy(5,'desc')
            ->buttons([Button::make('excel'), Button::make('print'), Button::make('reset'), Button::make('reload')]);
    }



    protected function getColumns()
    {
        return [
            Column::make('reference')->title('Reference'),
            Column::computed('from_warehouse')->title('From Warehouse')->orderable(false)->searchable(false),
            Column::computed('to_branch')->title('To Branch')->orderable(false)->searchable(false),
            Column::make('note')->title('Note'),
            Column::computed('status_badge')->title('Status')->orderable(false)->searchable(false),
            Column::make('created_at')->title('Created At'),
            Column::computed('cetak')->title('Cetak')->orderable(false)->searchable(false),
            Column::computed('action')->title('Action')->exportable(false)->printable(false)->width(180)->addClass('text-center'),
        ];
    }

    protected function filename()
    {
        return 'Incoming_Transfers_'.date('Ymd_His');
    }
}
