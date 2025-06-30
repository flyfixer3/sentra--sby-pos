<?php

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Modules\Transfer\Entities\TransferRequest;
use Modules\Transfer\Entities\TransferRequestItem;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;
use Modules\Mutation\Entities\Mutation;
use Carbon\Carbon;

class TransferController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('access_transfers'), 403);

        $transfers = TransferRequest::with(['fromWarehouse', 'toWarehouse', 'items'])->latest()->get();
        return view('transfer::index', compact('transfers'));
    }

    public function create()
    {
        abort_if(Gate::denies('create_transfers'), 403);

        $warehouses = Warehouse::where('branch_id', session('active_branch'))->get();
        $products = Product::all();

        return view('transfer::create', compact('warehouses', 'products'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_transfers'), 403);

        $request->validate([
            'reference' => 'required|string|max:255|unique:transfer_requests,reference',
            'date' => 'required|date',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'product_ids' => 'required|array',
            'quantities' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            $transfer = TransferRequest::create([
                'reference' => $request->reference,
                'date' => $request->date,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'note' => $request->note,
                'status' => 'pending',
                'branch_id' => session('active_branch'),
                'created_by' => auth()->id(),
            ]);

            foreach ($request->product_ids as $key => $product_id) {
                TransferRequestItem::create([
                    'transfer_request_id' => $transfer->id,
                    'product_id' => $product_id,
                    'quantity' => $request->quantities[$key],
                ]);

                // Insert mutation OUT from sender warehouse
                $prev = Mutation::where('product_id', $product_id)
                    ->where('warehouse_id', $request->from_warehouse_id)
                    ->latest()->first();

                Mutation::create([
                    'reference' => $request->reference,
                    'date' => $request->date,
                    'mutation_type' => 'Transfer',
                    'note' => 'Auto OUT from Transfer #' . $request->reference,
                    'warehouse_id' => $request->from_warehouse_id,
                    'product_id' => $product_id,
                    'stock_early' => $prev ? $prev->stock_last : 0,
                    'stock_in' => 0,
                    'stock_out' => $request->quantities[$key],
                    'stock_last' => ($prev ? $prev->stock_last : 0) - $request->quantities[$key],
                ]);
            }
        });

        toast('Transfer created successfully', 'success');
        return redirect()->route('transfers.index');
    }

    public function confirm(TransferRequest $transfer)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $prev = Mutation::where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->to_warehouse_id)
                    ->latest()->first();

                Mutation::create([
                    'reference' => $transfer->reference,
                    'date' => now(),
                    'mutation_type' => 'Transfer',
                    'note' => 'Auto IN from confirmed Transfer #' . $transfer->reference,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'product_id' => $item->product_id,
                    'stock_early' => $prev ? $prev->stock_last : 0,
                    'stock_in' => $item->quantity,
                    'stock_out' => 0,
                    'stock_last' => ($prev ? $prev->stock_last : 0) + $item->quantity,
                ]);
            }

            $transfer->update([
                'status' => 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => Carbon::now(),
            ]);
        });

        toast('Transfer confirmed and stock updated', 'success');
        return redirect()->route('transfers.index');
    }
}
