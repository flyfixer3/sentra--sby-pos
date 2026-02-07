<?php

namespace Modules\Mutation\DataTables;

use Modules\Mutation\Entities\Mutation;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Carbon\Carbon;

class MutationsDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)

            ->addColumn('warehouse_name', function ($row) {
                return optional($row->warehouse)->warehouse_name ?? '-';
            })

            // ✅ NEW: Rack Code/Name label
            ->addColumn('rack_label', function ($row) {
                $rack = $row->rack ?? null;
                if (!$rack) return '-';

                $code = trim((string) ($rack->code ?? ''));
                if ($code !== '') return $code;

                $name = trim((string) ($rack->name ?? ''));
                if ($name !== '') return $name;

                return 'Rack#' . (int) $rack->id;
            })

            ->addColumn('product_code', function ($row) {
                return optional($row->product)->product_code ?? '-';
            })
            ->addColumn('product_name', function ($row) {
                return optional($row->product)->product_name ?? '-';
            })

            // ✅ FORMAT NOTE: jadi Item note #1, #2, dst
            ->editColumn('note', function ($row) {
                $note = trim((string) ($row->note ?? ''));
                if ($note === '') return '-';

                // Normalisasi separator biar gampang diproses
                // Kita treat: " | " sebagai pemisah segmen
                $parts = preg_split('/\s*\|\s*/', $note);

                $headerParts = [];
                $items = [];

                foreach ($parts as $p) {
                    $p = trim((string) $p);
                    if ($p === '') continue;

                    // Deteksi item: "Item: xxx" (case-insensitive)
                    if (preg_match('/^item\s*:\s*(.+)$/i', $p, $m)) {
                        $items[] = trim((string) $m[1]);
                    } else {
                        $headerParts[] = $p;
                    }
                }

                $html = '';

                // header tetap ditampilkan (kalau ada)
                if (!empty($headerParts)) {
                    $html .= '<div class="text-muted small mb-1">' . e(implode(' | ', $headerParts)) . '</div>';
                }

                // kalau ada item -> tampilkan bernomor
                if (!empty($items)) {
                    $html .= '<ol class="mb-0 ps-3">';
                    foreach ($items as $i => $it) {
                        $num = $i + 1;
                        $html .= '<li><span class="fw-semibold">Item note #' . $num . ':</span> ' . e($it) . '</li>';
                    }
                    $html .= '</ol>';
                    return $html;
                }

                // fallback kalau ga ada "Item:" sama sekali
                return e($note);
            })

            ->editColumn('date', function ($row) {
                return $row->date ? Carbon::parse($row->date)->format('d-m-Y') : '-';
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y H:i:s') : '-';
            })

            // ✅ karena note sekarang HTML (ol/li)
            ->rawColumns(['note']);
    }

    public function query(Mutation $model)
    {
        $active = session('active_branch');
        $table  = $model->getTable();

        // base query + eager load
        // ✅ NEW: include rack eager load
        $q = $model->newQuery()
            ->with(['product', 'warehouse', 'rack'])
            ->orderByDesc($table.'.id');

        // kalau active_branch = 'all' => jangan pakai global scope branch
        if ($active === 'all') {
            $q->withoutGlobalScopes();
        }

        /**
         * ===== FILTERS (dibaca dari request Ajax DataTables) =====
         * - car (keyword mobil)
         * - warehouse_id
         * - mutation_type
         * - reference
         * - date_from, date_to
         * - rack_id (optional kalau mau dipakai nanti)
         */

        if (request()->filled('warehouse_id')) {
            $q->where($table.'.warehouse_id', (int) request('warehouse_id'));
        }

        if (request()->filled('mutation_type')) {
            $type = request('mutation_type');
            if (in_array($type, ['In','Out','Transfer'], true)) {
                $q->where($table.'.mutation_type', $type);
            }
        }

        if (request()->filled('reference')) {
            $ref = trim((string) request('reference'));
            $q->where($table.'.reference', 'like', "%{$ref}%");
        }

        // optional rack filter (kalau UI nanti ditambah)
        if (request()->filled('rack_id')) {
            $q->where($table.'.rack_id', (int) request('rack_id'));
        }

        // date range pakai kolom mutations.date (karena ada di tabel)
        if (request()->filled('date_from')) {
            $from = Carbon::parse(request('date_from'))->toDateString();
            $q->whereDate($table.'.date', '>=', $from);
        }

        if (request()->filled('date_to')) {
            $to = Carbon::parse(request('date_to'))->toDateString();
            $q->whereDate($table.'.date', '<=', $to);
        }

        // filter "mobil" => cari di product_code / product_name
        if (request()->filled('car')) {
            $car = trim((string) request('car'));

            $q->whereHas('product', function ($p) use ($car) {
                $p->where(function ($x) use ($car) {
                    $x->where('product_code', 'like', "%{$car}%")
                      ->orWhere('product_name', 'like', "%{$car}%");
                });
            });
        }

        return $q;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('mutation-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                  'tr' .
                  <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            // ✅ created_at sekarang index kolom terakhir (cek getColumns)
            ->orderBy(11, 'desc')
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
            Column::make('warehouse_name')->title('Warehouse')->orderable(false)->searchable(false)->className('text-center align-middle'),

            // ✅ NEW column
            Column::make('rack_label')->title('Rack')->orderable(false)->searchable(false)->className('text-center align-middle'),

            Column::make('product_code')->title('Code')->orderable(false)->searchable(false)->className('text-center align-middle'),
            Column::make('product_name')->title('Product / Mobil')->orderable(false)->searchable(false)->className('text-start align-middle'),

            Column::make('mutation_type')->className('text-center align-middle'),
            Column::make('reference')->className('text-center align-middle'),
            Column::make('note')->className('text-start align-middle'),

            Column::make('stock_early')->className('text-center align-middle'),
            Column::make('stock_in')->className('text-center align-middle'),
            Column::make('stock_out')->className('text-center align-middle'),
            Column::make('stock_last')->className('text-center align-middle'),

            Column::make('date')->title('Date')->className('text-center align-middle'),
            Column::make('created_at')->title('Created At')->className('text-center align-middle'),
        ];
    }

    protected function filename()
    {
        return 'Mutations_' . date('YmdHis');
    }
}
