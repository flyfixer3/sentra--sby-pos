<?php

namespace Modules\SaleOrder\DataTables;

use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Modules\SaleOrder\Entities\SaleOrder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class SaleOrdersDataTable extends DataTable
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

    private function formatEstimatedArrival($row): string
    {
        if (empty($row->estimated_arrival_date)) {
            return '-';
        }

        $date = Carbon::parse($row->estimated_arrival_date)->startOfDay();
        $today = now()->startOfDay();
        $days = $today->diffInDays($date, false);
        $dateText = $date->format('d-m-Y');

        if ($days < 0) {
            return '<span class="badge badge-danger">' . e($dateText . ' - Overdue ' . abs($days) . ' days') . '</span>';
        }

        if ($days === 0) {
            return '<span class="badge badge-danger">' . e($dateText . ' - Due Today') . '</span>';
        }

        if ($days <= 3) {
            return '<span class="badge badge-danger">' . e($dateText . ' - ' . $days . ' days left') . '</span>';
        }

        if ($days <= 7) {
            return '<span class="badge badge-warning">' . e($dateText . ' - ' . $days . ' days left') . '</span>';
        }

        return '<span class="badge badge-light border">' . e($dateText . ' - ' . $days . ' days left') . '</span>';
    }

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addRowAttr('data-href', function ($row) {
                return route('sale-orders.show', $row->id);
            })
            ->addColumn('customer', fn ($row) => e($row->customer?->customer_name ?? '-'))
            ->editColumn('date', function ($row) {
                return $this->formatDateWithCreatedTime($row, 'date');
            })
            ->editColumn('status', function ($row) {
                $s = strtolower((string) ($row->status ?? 'pending'));

                $confirmedCount = (int) ($row->confirmed_deliveries_count ?? 0);
                $invoicedCount  = (int) ($row->invoiced_confirmed_deliveries_count ?? 0);

                $allConfirmedInvoiced = ($confirmedCount > 0 && $confirmedCount === $invoicedCount);

                // ✅ display correction:
                // - kalau status delivered tapi belum semua invoiced -> tetap delivered
                // - kalau status delivered dan semua invoiced -> completed
                // - kalau status completed tapi ternyata belum semua invoiced -> turun ke delivered
                if ($s === 'delivered') {
                    $s = $allConfirmedInvoiced ? 'completed' : 'delivered';
                } elseif ($s === 'completed') {
                    $s = $allConfirmedInvoiced ? 'completed' : 'delivered';
                }

                $badge = 'secondary';
                if ($s === 'pending') $badge = 'warning';
                if ($s === 'partial_delivered') $badge = 'info';
                if ($s === 'delivered') $badge = 'primary';
                if ($s === 'completed') $badge = 'success';
                if ($s === 'cancelled') $badge = 'danger';

                return '<span class="badge badge-' . $badge . '">' . strtoupper(e($s)) . '</span>';
            })
            ->addColumn('shortage_status', function ($row) {
                if ((bool) ($row->has_shortage ?? false)) {
                    $qty = $row->shortage_quantity;
                    $qtyText = is_null($qty)
                        ? '<div class="small text-muted mt-1">Shortage: Not recorded</div>'
                        : '<div class="small text-danger mt-1">Shortage: ' . number_format((int) $qty) . ' qty</div>';

                    return '<span class="badge badge-danger">Pending Stock</span>' . $qtyText;
                }

                return '<span class="badge badge-success">Available</span><div class="small text-muted mt-1">Shortage: 0 qty</div>';
            })
            ->editColumn('estimated_arrival_date', function ($row) {
                return $this->formatEstimatedArrival($row);
            })
            ->addColumn('action', function ($row) {

                $status = strtolower((string) ($row->status ?? 'pending'));
                $isPending = ($status === 'pending');

                $showUrl = route('sale-orders.show', $row->id);
                $editUrl = route('sale-orders.edit', $row->id);
                $deleteUrl = route('sale-orders.destroy', $row->id);

                $html = '<div class="btn-group" role="group" aria-label="Action">';

                // VIEW (show)
                if (Gate::allows('show_sale_orders')) {
                    $html .= '<a class="btn btn-sm btn-outline-primary" href="' . $showUrl . '">
                                <i class="bi bi-eye"></i>
                              </a>';
                } else {
                    // fallback kalau permission show belum ada / belum dipakai
                    $html .= '<a class="btn btn-sm btn-outline-primary" href="' . $showUrl . '">
                                <i class="bi bi-eye"></i>
                              </a>';
                }

                // EDIT (only pending)
                if ($isPending && Gate::allows('edit_sale_orders')) {
                    $html .= '<a class="btn btn-sm btn-outline-secondary" href="' . $editUrl . '">
                                <i class="bi bi-pencil"></i>
                              </a>';
                }

                // DELETE (only pending)
                if ($isPending && Gate::allows('delete_sale_orders')) {
                    $html .= '<form action="' . $deleteUrl . '" method="POST" style="display:inline-block"
                                data-confirm-submit="true"
                                data-confirm-title="Confirm Delete"
                                data-confirm-message="Delete this Sale Order? This cannot be undone."
                                data-confirm-button="Delete"
                                data-confirm-variant="danger">
                                ' . csrf_field() . '
                                ' . method_field('DELETE') . '
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                              </form>';
                }

                $html .= '</div>';

                return $html;
            })
            ->orderColumn('shortage_status', 'COALESCE(sale_orders.shortage_quantity, 0) $1')
            ->orderColumn('estimated_arrival_date', 'CASE WHEN sale_orders.estimated_arrival_date IS NULL THEN 1 ELSE 0 END ASC, sale_orders.estimated_arrival_date $1')
            ->rawColumns(['status', 'shortage_status', 'estimated_arrival_date', 'action']);
    }

    public function query(SaleOrder $model)
    {
        $query = $model->query()
            ->with(['customer'])
            ->select('sale_orders.*')
            ->selectSub(function ($q) {
                $q->from('sale_deliveries as sd')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('sd.sale_order_id', 'sale_orders.id')
                    ->whereColumn('sd.branch_id', 'sale_orders.branch_id')
                    ->whereRaw('LOWER(COALESCE(sd.status,"")) = ?', ['confirmed']);
            }, 'confirmed_deliveries_count')
            ->selectSub(function ($q) {
                $q->from('sale_deliveries as sd')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('sd.sale_order_id', 'sale_orders.id')
                    ->whereColumn('sd.branch_id', 'sale_orders.branch_id')
                    ->whereRaw('LOWER(COALESCE(sd.status,"")) = ?', ['confirmed'])
                    ->whereNotNull('sd.sale_id');
            }, 'invoiced_confirmed_deliveries_count');

        $shortageFilter = request('shortage_filter', 'all');
        if ($shortageFilter === 'shortage') {
            $query->where('sale_orders.has_shortage', true);
        } elseif ($shortageFilter === 'normal') {
            $query->where('sale_orders.has_shortage', false);
        }

        return $query;
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('sale-orders-table')
            ->columns($this->getColumns())
            ->ajax([
                'url' => route('sale-orders.index'),
                'type' => 'GET',
                'data' => 'function(d){ d.shortage_filter = $("#sale-order-shortage-filter").val(); }',
            ])
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>>" .
                  "tr" .
                  "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(7, 'desc')
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
            Column::make('reference')->title('Reference'),
            Column::make('date')->title('Date'),
            Column::computed('customer')->title('Customer')->orderable(false)->searchable(false),
            Column::make('status')->title('Status'),
            Column::computed('shortage_status')->title('Stock')->orderable(true)->searchable(false),
            Column::make('estimated_arrival_date')->title('Estimated Arrival')->searchable(false),
            Column::computed('action')->exportable(false)->printable(false)->width(120)->addClass('text-center'),
            Column::make('created_at')->visible(false),
        ];
    }

    protected function filename()
    {
        return 'SaleOrders_' . date('YmdHis');
    }
}
