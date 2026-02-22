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

            // sekarang warehouse_name sudah hasil JOIN (alias), jadi tinggal pakai langsung
            ->editColumn('warehouse_name', function ($row) {
                $name = trim((string)($row->warehouse_name ?? ''));
                return $name !== '' ? $name : '-';
            })

            // rack_label juga dari JOIN (rack_code / rack_name)
            ->editColumn('rack_label', function ($row) {
                $code = trim((string)($row->rack_code ?? ''));
                if ($code !== '') return $code;

                $name = trim((string)($row->rack_name ?? ''));
                if ($name !== '') return $name;

                $rid = (int)($row->rack_id ?? 0);
                return $rid > 0 ? ('Rack#' . $rid) : '-';
            })

            // product_code & product_name sudah dari JOIN juga
            ->editColumn('product_code', function ($row) {
                $v = trim((string)($row->product_code ?? ''));
                return $v !== '' ? $v : '-';
            })
            ->editColumn('product_name', function ($row) {
                $v = trim((string)($row->product_name ?? ''));
                return $v !== '' ? $v : '-';
            })

            // ✅ FORMAT NOTE: jadi Item note #1, #2, dst (ini tetap sama, tapi aku pindah jadi editColumn biar konsisten)
            ->editColumn('note', function ($row) {
                $note = trim((string) ($row->note ?? ''));
                if ($note === '') return '-';

                $parts = preg_split('/\s*\|\s*/', $note);

                $headerParts = [];
                $items = [];

                foreach ($parts as $p) {
                    $p = trim((string) $p);
                    if ($p === '') continue;

                    if (preg_match('/^item\s*:\s*(.+)$/i', $p, $m)) {
                        $items[] = trim((string) $m[1]);
                    } else {
                        $headerParts[] = $p;
                    }
                }

                $html = '';

                if (!empty($headerParts)) {
                    $html .= '<div class="text-muted small mb-1">' . e(implode(' | ', $headerParts)) . '</div>';
                }

                if (!empty($items)) {
                    $html .= '<ol class="mb-0 ps-3">';
                    foreach ($items as $i => $it) {
                        $num = $i + 1;
                        $html .= '<li><span class="fw-semibold">Item note #' . $num . ':</span> ' . e($it) . '</li>';
                    }
                    $html .= '</ol>';
                    return $html;
                }

                return e($note);
            })

            ->editColumn('date', function ($row) {
                return $row->date ? Carbon::parse($row->date)->format('d-m-Y') : '-';
            })
            ->editColumn('created_at', function ($row) {
                return $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y H:i:s') : '-';
            })

            // ✅ Karena note HTML
            ->rawColumns(['note'])

            /**
             * ✅ INI PENTING:
             * - Biar DataTables bisa search/sort untuk kolom computed,
             *   kita map kolom virtual ke kolom DB hasil join.
             */
            ->filterColumn('warehouse_name', function ($q, $keyword) {
                $keyword = trim((string)$keyword);
                if ($keyword === '') return;
                $q->where('warehouses.warehouse_name', 'like', "%{$keyword}%");
            })
            ->orderColumn('warehouse_name', function ($q, $order) {
                $q->orderBy('warehouses.warehouse_name', $order);
            })

            ->filterColumn('rack_label', function ($q, $keyword) {
                $keyword = trim((string)$keyword);
                if ($keyword === '') return;

                $q->where(function ($x) use ($keyword) {
                    $x->where('racks.code', 'like', "%{$keyword}%")
                    ->orWhere('racks.name', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('rack_label', function ($q, $order) {
                // prefer code, fallback name
                $q->orderByRaw("COALESCE(NULLIF(racks.code,''), NULLIF(racks.name,''), '') {$order}");
            })

            ->filterColumn('product_code', function ($q, $keyword) {
                $keyword = trim((string)$keyword);
                if ($keyword === '') return;
                $q->where('products.product_code', 'like', "%{$keyword}%");
            })
            ->orderColumn('product_code', function ($q, $order) {
                $q->orderBy('products.product_code', $order);
            })

            ->filterColumn('product_name', function ($q, $keyword) {
                $keyword = trim((string)$keyword);
                if ($keyword === '') return;
                $q->where('products.product_name', 'like', "%{$keyword}%");
            })
            ->orderColumn('product_name', function ($q, $order) {
                $q->orderBy('products.product_name', $order);
            });
    }

    public function query(Mutation $model)
    {
        $active = session('active_branch');
        $table  = $model->getTable(); // mutations

        // ✅ base query: JOIN supaya sorting/search untuk kolom warehouse/rack/product bisa jalan
        $q = $model->newQuery()
            ->select([
                $table . '.*',
                'warehouses.warehouse_name as warehouse_name',
                'products.product_code as product_code',
                'products.product_name as product_name',
                'racks.code as rack_code',
                'racks.name as rack_name',
            ])
            ->leftJoin('warehouses', 'warehouses.id', '=', $table . '.warehouse_id')
            ->leftJoin('products', 'products.id', '=', $table . '.product_id')
            ->leftJoin('racks', 'racks.id', '=', $table . '.rack_id');

        // kalau active_branch = 'all' => jangan pakai global scope branch
        if ($active === 'all') {
            $q->withoutGlobalScopes();
        }

        /**
         * ===== FILTERS (dibaca dari request Ajax DataTables) =====
         */
        if (request()->filled('warehouse_id')) {
            $q->where($table . '.warehouse_id', (int) request('warehouse_id'));
        }

        if (request()->filled('mutation_type')) {
            $type = request('mutation_type');
            if (in_array($type, ['In', 'Out', 'Transfer'], true)) {
                $q->where($table . '.mutation_type', $type);
            }
        }

        if (request()->filled('reference')) {
            $ref = trim((string) request('reference'));
            $q->where($table . '.reference', 'like', "%{$ref}%");
        }

        if (request()->filled('rack_id')) {
            $q->where($table . '.rack_id', (int) request('rack_id'));
        }

        // date range pakai kolom mutations.date
        if (request()->filled('date_from')) {
            $from = Carbon::parse(request('date_from'))->toDateString();
            $q->whereDate($table . '.date', '>=', $from);
        }

        if (request()->filled('date_to')) {
            $to = Carbon::parse(request('date_to'))->toDateString();
            $q->whereDate($table . '.date', '<=', $to);
        }

        // filter "mobil" => cari di product_code / product_name (join)
        if (request()->filled('car')) {
            $car = trim((string) request('car'));
            $q->where(function ($x) use ($car) {
                $x->where('products.product_code', 'like', "%{$car}%")
                ->orWhere('products.product_name', 'like', "%{$car}%");
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
            // ✅ created_at kolom terakhir (0-based = 12)
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
            Column::make('warehouse_name')
                ->title('Warehouse')
                ->name('warehouses.warehouse_name')
                ->orderable(true)
                ->searchable(true)
                ->className('text-center align-middle'),

            Column::make('rack_label')
                ->title('Rack')
                // sorting/search rack_label kita mapping via orderColumn/filterColumn,
                // tapi tetap kasih name biar DataTables gak bikin order by "rack_label" mentah
                ->name('racks.code')
                ->orderable(true)
                ->searchable(true)
                ->className('text-center align-middle'),

            Column::make('product_code')
                ->title('Code')
                ->name('products.product_code')
                ->orderable(true)
                ->searchable(true)
                ->className('text-center align-middle'),

            Column::make('product_name')
                ->title('Product / Mobil')
                ->name('products.product_name')
                ->orderable(true)
                ->searchable(true)
                ->className('text-start align-middle'),

            Column::make('mutation_type')
                ->title('Mutation Type')
                ->name('mutations.mutation_type')
                ->orderable(true)
                ->searchable(true)
                ->className('text-center align-middle'),

            Column::make('reference')
                ->title('Reference')
                ->name('mutations.reference')
                ->orderable(true)
                ->searchable(true)
                ->className('text-center align-middle'),

            Column::make('note')
                ->title('Note')
                ->name('mutations.note')
                ->orderable(false)
                ->searchable(true)
                ->className('text-start align-middle'),

            Column::make('stock_early')
                ->title('Stock Early')
                ->name('mutations.stock_early')
                ->orderable(true)
                ->searchable(false)
                ->className('text-center align-middle'),

            Column::make('stock_in')
                ->title('Stock In')
                ->name('mutations.stock_in')
                ->orderable(true)
                ->searchable(false)
                ->className('text-center align-middle'),

            Column::make('stock_out')
                ->title('Stock Out')
                ->name('mutations.stock_out')
                ->orderable(true)
                ->searchable(false)
                ->className('text-center align-middle'),

            Column::make('stock_last')
                ->title('Stock Last')
                ->name('mutations.stock_last')
                ->orderable(true)
                ->searchable(false)
                ->className('text-center align-middle'),

            Column::make('date')
                ->title('Date')
                ->name('mutations.date')
                ->orderable(true)
                ->searchable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')
                ->title('Created At')
                ->name('mutations.created_at')
                ->orderable(true)
                ->searchable(false)
                ->className('text-center align-middle'),
        ];
    }

    protected function filename()
    {
        return 'Mutations_' . date('YmdHis');
    }
}
