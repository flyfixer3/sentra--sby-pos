<?php

namespace App\DataTables;

use App\Models\Entity;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EntityDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->editColumn('is_active', fn ($data) => $data->is_active ? 'Active' : 'Inactive')
            ->addColumn('branches_count', fn ($data) => (int) $data->branches_count)
            ->addColumn('action', function ($data) {
                return view('entities.partials.actions', compact('data'));
            });
    }

    public function query(Entity $model)
    {
        return $model->newQuery()->withCount('branches');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('entities-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .'tr' .<'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
            ->orderBy(0)
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
            Column::make('name')->className('text-center align-middle'),
            Column::make('code')->className('text-center align-middle'),
            Column::make('description')->className('text-center align-middle'),
            Column::computed('branches_count')->title('Branches')->className('text-center align-middle'),
            Column::make('is_active')->title('Status')->className('text-center align-middle'),
            Column::computed('action')->exportable(false)->printable(false)->className('text-center align-middle'),
        ];
    }

    protected function filename()
    {
        return 'Entities_' . date('YmdHis');
    }
}
