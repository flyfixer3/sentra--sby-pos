<?php

namespace Modules\Transfer\DataTables;

use Carbon\Carbon;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Modules\Transfer\Entities\TransferRequest as Transfer;

class OutgoingTransfersDataTable extends DataTable
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
            ->addColumn('from_warehouse', fn ($row) => optional($row->fromWarehouse)->name ?? '-')
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
            ->addColumn('action', fn ($row) => view('transfer::partials.actions-outgoing', compact('row')))
            ->editColumn('created_at', fn ($row) => $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y H:i:s') : '-')
            ->rawColumns(['action','status_badge']);
    }

    /** Outgoing = record milik cabang aktif (sender). */
    public function query(Transfer $model)
    {
        $active = $this->activeBranchId();
        $table  = $model->getTable();

        return $model->newQuery()
            ->with(['fromWarehouse','toBranch'])
            ->when(\Illuminate\Support\Facades\Schema::hasColumn($table, 'branch_id'), function ($q) use ($table, $active) {
                $q->where($table.'.branch_id', $active);
            })
            ->orderByDesc($table.'.created_at');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('outgoing-transfers-table')
            ->columns($this->getColumns())
            ->ajax(['url'=>route('transfers.datatable.outgoing'),'type'=>'GET'])
            ->dom('Bfrtip')
            ->orderBy(5,'desc')
            ->buttons([Button::make('excel'), Button::make('print'), Button::make('reset'), Button::make('reload')])
            ->parameters([
                'processing'  => true,
                'serverSide'  => true,
                'responsive'  => true,
                'autoWidth'   => false,
                'pageLength'  => 10,
                'lengthMenu'  => [10, 25, 50, 100],
                'language'    => [
                    'emptyTable'   => 'Tidak ada data transfer yang dapat ditampilkan.',
                    'zeroRecords'  => 'Data tidak ditemukan.',
                    'info'         => 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                    'infoEmpty'    => 'Menampilkan 0 data',
                    'infoFiltered' => '(difilter dari total _MAX_ data)',
                    'lengthMenu'   => 'Tampilkan _MENU_ data',
                    'search'       => 'Cari:',
                    'paginate'     => [
                        'first'    => 'Pertama',
                        'last'     => 'Terakhir',
                        'next'     => 'Berikutnya',
                        'previous' => 'Sebelumnya',
                    ],
                ],
            ]);
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
            Column::computed('action')->title('Action')->exportable(false)->printable(false)->width(160)->addClass('text-center'),
        ];
    }

    protected function filename()
    {
        return 'Outgoing_Transfers_'.date('Ymd_His');
    }
}
