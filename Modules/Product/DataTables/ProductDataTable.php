<?php

namespace Modules\Product\DataTables;

use Modules\Product\Entities\Product;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Modules\Mutation\Entities\Mutation;

class ProductDataTable extends DataTable
{

    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)->with('category')
            ->addColumn('action', function ($data) {
                return view('product::products.partials.actions', compact('data'));
            })
            ->addColumn('product_image', function ($data) {
                $url = $data->getFirstMediaUrl('images', 'thumb');
                // dd($data);
                return '<img src="'.$url.'" border="0" width="50" class="img-thumbnail" align="center"/>';
            })
            ->editColumn('category.category_code', function ($data) {
                $temp = Mutation::with('warehouse')->where('product_id',$data->id)
                    ->latest()
                    ->get()
                    ->unique('warehouse_id')
                    ->sortByDesc('stock_last')->pluck('warehouse')->pluck('warehouse_name');

                // $str ="test";
                // dd($temp);
                // if($temp != null){
                    // foreach ($temp as $index => $value) {
                    //     if($index == array_key_first($temp)){
                    //         $str="";
                    //         $str .= $value->warehouse_name;
                    //     }else{
                    //         $str .= ", ".$value->warehouse_name;
                    //     }
                    // }
                return count($temp) > 0 ? $temp : '-';
            })
            ->editColumn('product_price', function ($data) {
                return format_currency($data->product_price);
            })
            ->editColumn('product_quantity', function ($data) {
                return $data->product_quantity . ' ' . $data->product_unit;
            })
            ->rawColumns(['product_image']);
    }

    public function query(Product $model)
    {
        return $model->newQuery()->with('category', 'accessory');
    }

    public function html()
    {
        return $this->builder()
                    ->setTableId('product-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->dom("<'row'<'col-md-3'l><'col-md-5 mb-2'B><'col-md-4'f>> .
                                'tr' .
                                <'row'<'col-md-5'i><'col-md-7 mt-2'p>>")
                    ->orderBy(7)
                    ->buttons(
                        Button::make('excel')
                            ->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),
                        Button::make('print')
                            ->text('<i class="bi bi-printer-fill"></i> Print'),
                        Button::make('reset')
                            ->text('<i class="bi bi-x-circle"></i> Reset'),
                        Button::make('reload')
                            ->text('<i class="bi bi-arrow-repeat"></i> Reload')
                    );
    }

    protected function getColumns()
    {
        return [
            Column::computed('product_image')
                ->title('Image')
                ->className('text-center align-middle'),
            Column::make('product_name')
                ->title('Name')
                ->className('text-center align-middle'),

            Column::make('product_code')
                ->title('Code')
                ->className('text-center align-middle'),

            Column::make('product_price')
                ->title('Price')
                ->className('text-center align-middle'),

            Column::make('product_quantity')
                ->title('Quantity')
                ->className('text-center align-middle'),

            Column::make('category.category_code')
                ->title('Rack')
                ->className('text-center align-middle'),

            Column::make('accessory.accessory_code')
            ->title('Accessory')
            ->className('text-center align-middle'),

            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->className('text-center align-middle'),

            Column::make('created_at')
                ->visible(false)
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'Product_' . date('YmdHis');
    }
}
