<?php

namespace App\Http\Livewire;

use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Mutation\Entities\Mutation;
use Illuminate\Support\Facades\DB;

class SearchProductSale extends Component
{

    public $query;
    public $search_results;
    public $how_many;

    public function mount() {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function render() {
        return view('livewire.search-product-sale');
    }

    public function updatedQuery() {

        // $this->search_results = Mutation::select(DB::raw('t.*'))
        // ->from((DB::raw('(SELECT * FROM mutations ORDER BY stock_last ASC) t')))
        // ->groupBy('t.product_id')->get();

        $sub = Mutation::orderBy('date','asc');

        $warehouse_ids = Warehouse::all()->modelKeys();

        // foreach ($warehouse_ids as $id) {
            $this->search_results = $this->search_results->concat(Mutation::with('product','warehouse')
                // ->where('warehouse_id', $id)
                ->whereHas('product', function($q){
                    $q->where('product_name', 'like', '%' . $this->query . '%')
                    ->orWhere('product_code', 'like', '%' . $this->query . '%');
                })
                ->latest()
                ->get()
                ->unique('warehouse_id'));
        // }
        // dd($this->search_results);

        // $this->search_results = DB::table(DB::raw("({$sub->toSql()}) as sub"))
        //         ->groupBy('product_id')
        //         ->get();
                
                // dd($this->search_results);

        // $this->search_results = DB::table('mutations as m')
        //     ->select('m.*')
        //     ->join('product as p', function($join){
        //         $join->on('m.product_id', '=', 'p.id')
        //             ->whereRaw(DB::raw('p.product_name like %'.$this->query.'% OR p.product_code like %'.$this->query.'%'));
        //     })
            // ->leftJoin('mutations as m1', function ($join) {
            //     $join->on('m.product_id', '=', 'm1.product_id')
            //     ->whereRaw(DB::raw('m.created_at < m1.created_at'));
            // })
            // ->whereNull('m1.product_id')
            // ->orderBy('m.created_at', 'DESC')
            // ->get();
        // $this->search_results = Mutation::oldest('date')->with(['warehouse', 'product' => function($query){
        // }])
        // ->whereHas('product', function($q){
        //     $q->where('product_name', 'like', '%' . $this->query . '%')->orWhere('product_code', 'like', '%' . $this->query . '%');
        //     $q->groupBy('id');
        // })
        // ->take($this->how_many)->get();

        // $this->search_results = Product::where('product_name', 'like', '%' . $this->query . '%')
        //     ->orWhere('product_code', 'like', '%' . $this->query . '%')
        //     ->take($this->how_many)->get();
    }

    public function loadMore() {
        $this->how_many += 5;
        $this->updatedQuery();
    }

    public function resetQuery() {
        $this->query = '';
        $this->how_many = 5;
        $this->search_results = Collection::empty();
    }

    public function selectProduct($product) {
        $this->emit('productSelected', $product);
    }
}
