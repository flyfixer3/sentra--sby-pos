<?php

namespace Modules\Mutation\Http\Controllers;

use Modules\Mutation\DataTables\MutationsDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Mutation\Entities\Mutation;
use Modules\Product\Entities\Product;
use Modules\Product\Notifications\NotifyQuantityAlert;

class MutationController extends Controller
{

    public function index(MutationsDataTable $dataTable) {
        abort_if(Gate::denies('access_mutations'), 403);

        return $dataTable->render('mutation::index');
    }


    public function create() {
        abort_if(Gate::denies('create_mutations'), 403);

        return view('mutation::create');
    }

    public function readCSV($csvFile, $array)
    {
        $file_handle = fopen($csvFile, 'r');
        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle, 0, $array['delimiter']);
        }
        fclose($file_handle);
        return $line_of_text;
    }
    
    public function store(Request $request) {

        abort_if(Gate::denies('create_mutations'), 403);
        // dd(Mutation::where('product_id', 1716)
        //                                 ->latest()->get()->unique('warehouse_id')->sum('stock_in'));


        // $csvFileName = "KONSINYASI.csv";
        // $csvFile = public_path('csv/' . $csvFileName);
        // $konsyinasi = $this->readCSV($csvFile,array('delimiter' => ','));

        // foreach($konsyinasi as $ks){
        //     $product = Product::where('product_code', $ks[1])->first();
        //     if(!$product){
        //         $product = Product::create([
        //             'category_id' => 99,
        //             'accessory_code' => '-',
        //             'product_name' => 'dummy',
        //             'product_code' => $ks[1],
        //             'product_barcode_symbology' => 'C128',
        //             'product_price' => 0,
        //             'product_cost' => 0,
        //             'product_unit' => 'Unit',
        //             'product_quantity' => 0,
        //             'product_stock_alert' => -1,
        //         ]);
        //     }
        //     $mutation = Mutation::where('product_id', $product->id)
        //             ->where('warehouse_id', 2)
        //             ->latest()->first();
        //     $_stock_early = $mutation ? $mutation->stock_last : 0;
        //     $_stock_in = $ks[2];
        //     $_stock_out = 0;
        //     $_stock_last = $_stock_early + $_stock_in;
        //     $mutation = Mutation::create([
        //         'reference' => $ks[0],
        //         'date' => $request->date,
        //         'mutation_type' => 'In',
        //         'note' => $request->note,
        //         'warehouse_id' => 2,
        //         'product_id' => $product->id,
        //         'stock_early' => $_stock_early,
        //         'stock_in' => $_stock_in,
        //         'stock_out'=> $_stock_out,
        //         'stock_last'=> $_stock_last,
        //     ]);

            
        //     $product->update([
        //         'product_quantity' => Mutation::where('product_id', $product->id)
        //                                 ->latest()->get()->unique('warehouse_id')
        //                                 ->sum('stock_last'),
        //     ]);
        // }
        // $request->validate([
        //     'reference'   => 'required|string|max:255',
        //     'date'        => 'required|date',
        //     'note'        => 'nullable|string|max:1000',
        //     'product_ids' => 'required',
        //     'quantities'  => 'required',
        //     'type'       => 'required'
        // ]);
        

        DB::transaction(function () use ($request) {
            if($request->mutation_type == "Out"){
                foreach ($request->product_ids as $key => $id) {
                    $mutation = Mutation::where('product_id', $id)
                    ->where('warehouse_id', $request->warehouse_out_id)
                    ->latest()->first();
                    $_stock_early = $mutation ? $mutation->stock_last : 0;
                    $_stock_in = 0;
                    $_stock_out = $request->quantities[$key];
                    $_stock_last = $_stock_early - $_stock_out;
                    $number = Mutation::max('id') + 1;
                    
                    $mutation = Mutation::create([
                        'reference' => $request->reference,
                        'date' => $request->date,
                        'mutation_type' => $request->mutation_type,
                        'note' => $request->note,
                        'warehouse_id' => $request->warehouse_out_id,
                        'product_id' => $id,
                        'stock_early' => $_stock_early,
                        'stock_in' => $_stock_in,
                        'stock_out'=> $_stock_out,
                        'stock_last'=> $_stock_last,
                    ]);
                }
            }else if($request->mutation_type == "In"){
                foreach ($request->product_ids as $key => $id) {
                    $mutation = Mutation::where('product_id', $id)
                    ->where('warehouse_id', $request->warehouse_in_id)->latest()->first();
                    $_stock_early = $mutation ? $mutation->stock_last : 0;
                    $_stock_in = $request->quantities[$key];
                    $_stock_out = 0;
                    $_stock_last = $_stock_early + $_stock_in;
                    $number = Mutation::max('id') + 1;
                    
                    $mutation = Mutation::create([
                        'reference' => $request->reference,
                        'date' => $request->date,
                        'mutation_type' => $request->mutation_type,
                        'note' => $request->note,
                        'warehouse_id' => $request->warehouse_in_id,
                        'product_id' => $id,
                        'stock_early' => $_stock_early,
                        'stock_in' => $_stock_in,
                        'stock_out'=> $_stock_out,
                        'stock_last'=> $_stock_last,
                    ]);
                }
            }else if($request->mutation_type == "Transfer"){
                foreach ($request->product_ids as $key => $id) {
                    $mutation = Mutation::where('product_id', $id)
                    ->where('warehouse_id', $request->warehouse_out_id)
                    ->latest()->first();
                    $_stock_early = $mutation ? $mutation->stock_last : 0;
                    $_stock_in = 0;
                    $_stock_out = $request->quantities[$key];
                    $_stock_last = $_stock_early - $_stock_out;
                    $number = Mutation::max('id') + 1;
                    
                    $mutation = Mutation::create([
                        'reference' => $request->reference,
                        'date' => $request->date,
                        'mutation_type' => $request->mutation_type,
                        'note' => $request->note,
                        'warehouse_id' => $request->warehouse_out_id,
                        'product_id' => $id,
                        'stock_early' => $_stock_early,
                        'stock_in' => $_stock_in,
                        'stock_out'=> $_stock_out,
                        'stock_last'=> $_stock_last,
                    ]);

                    $_stock_early = $mutation ? $mutation->stock_last : 0;
                    $_stock_in = $request->quantities[$key];
                    $_stock_out = 0;
                    $_stock_last = $_stock_early + $_stock_in;
                    
                    $mutation = Mutation::create([
                        'reference' => $request->reference,
                        'date' => $request->date,
                        'mutation_type' => $request->mutation_type,
                        'note' => $request->note,
                        'warehouse_id' => $request->warehouse_in_id,
                        'product_id' => $id,
                        'stock_early' => $_stock_early,
                        'stock_in' => $_stock_in,
                        'stock_out'=> $_stock_out,
                        'stock_last'=> $_stock_early + $request->stock_in - $request->stock_out,
                    ]);
                }
            }
            
        });

        toast('Mutation Created!', 'success');

        return redirect()->route('mutations.index');
    }


    public function show(Mutation $mutation) {
        abort_if(Gate::denies('show_mutations'), 403);

        return view('mutation::show', compact('mutation'));
    }


    public function edit(Mutation $mutation) {
        abort_if(Gate::denies('edit_mutations'), 403);

        return view('mutation::edit', compact('mutation'));
    }


    public function update(Request $request, Mutation $mutation) {
        abort_if(Gate::denies('edit_mutations'), 403);

        $request->validate([
            'reference'   => 'required|string|max:255',
            'date'        => 'required|date',
            'note'        => 'nullable|string|max:1000',
            'product_ids' => 'required',
            'quantities'  => 'required',
            'types'       => 'required'
        ]);

        DB::transaction(function () use ($request, $mutation) {
            $mutation->update([
                'reference' => $request->reference,
                'date'      => $request->date,
                'note'      => $request->note
            ]);

            foreach ($mutation->adjustedProducts as $adjustedProduct) {
                $product = Product::findOrFail($adjustedProduct->product->id);

                if ($adjustedProduct->type == 'add') {
                    $product->update([
                        'product_quantity' => $product->product_quantity - $adjustedProduct->quantity
                    ]);
                } elseif ($adjustedProduct->type == 'sub') {
                    $product->update([
                        'product_quantity' => $product->product_quantity + $adjustedProduct->quantity
                    ]);
                }

                $adjustedProduct->delete();
            }

            foreach ($request->product_ids as $key => $id) {
                Mutation::create([
                    'mutation_id' => $mutation->id,
                    'product_id'    => $id,
                    'quantity'      => $request->quantities[$key],
                    'type'          => $request->types[$key]
                ]);

                $product = Product::findOrFail($id);

                if ($request->types[$key] == 'add') {
                    $product->update([
                        'product_quantity' => $product->product_quantity + $request->quantities[$key]
                    ]);
                } elseif ($request->types[$key] == 'sub') {
                    $product->update([
                        'product_quantity' => $product->product_quantity - $request->quantities[$key]
                    ]);
                }
            }
        });

        toast('Mutation Updated!', 'info');

        return redirect()->route('mutations.index');
    }


    public function destroy(Mutation $mutation) {
        abort_if(Gate::denies('delete_mutations'), 403);

        $mutation->delete();

        toast('Mutation Deleted!', 'warning');

        return redirect()->route('mutations.index');
    }
}
