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
use Modules\Transfer\DataTables\TransfersDataTable;
use Modules\Setting\Entities\Setting;
use Modules\Transfer\Entities\PrintLog; // kita buat di step 6
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;


class TransferController extends Controller
{
    public function index(TransfersDataTable $dataTable)
    {
        abort_if(Gate::denies('access_transfers'), 403);

        return $dataTable->render('transfer::index');
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
            'to_branch_id' => 'required|exists:branches,id|different:' . session('active_branch'),
            'product_ids' => 'required|array',
            'quantities' => 'required|array',
        ]);

        DB::transaction(function () use ($request) {
            $transfer = TransferRequest::create([
                'reference' => $request->reference,
                'date' => $request->date,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_branch_id' => $request->to_branch_id,
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
                    'stock_early' => $prev?->stock_last ?? 0,
                    'stock_in' => 0,
                    'stock_out' => $request->quantities[$key],
                    'stock_last' => ($prev?->stock_last ?? 0) - $request->quantities[$key],
                ]);
            }
        });

        toast('Transfer created successfully', 'success');
        return redirect()->route('transfers.index');
    }

    public function show($id)
    {
        $transfer = TransferRequest::with([
            'fromWarehouse', 'toWarehouse', 'toBranch', 'creator', 'confirmedBy', 'items.product'
        ])->findOrFail($id);

        return view('transfer::show', compact('transfer'));
    }




    public function printPdf($id)
    {
        $transfer = TransferRequest::with(['items.product', 'fromWarehouse', 'toBranch'])->findOrFail($id);
        $setting = Setting::first();
        $user = Auth::user();

        // Jika belum pernah dicetak
        if (!$transfer->printed_at) {
            $transfer->printed_at = now();
            $transfer->printed_by = $user->id;
            $transfer->status = 'shipped';
            $transfer->save();
        }

        // Jika sudah pernah dicetak → hanya boleh admin/supervisor
        if ($transfer->printed_at && $transfer->status !== 'pending') {
            if (!$user->hasAnyRole(['Super Admin', 'Administrator', 'Supervisor'])) {
                abort(403, 'Hanya pengguna dengan hak akses yang dapat mencetak ulang surat jalan.');
            }
        }
        // Log aktivitas cetak
        PrintLog::create([
            'user_id' => $user->id,
            'transfer_request_id' => $transfer->id,
            'printed_at' => now(),
            'ip_address' => request()->ip(),
        ]);

        $pdf = Pdf::loadView('transfer::print', compact('transfer', 'setting'))
                ->setPaper('A4', 'portrait');

        return $pdf->download("Surat_Jalan_{$transfer->reference}.pdf");
    }






    // ✅ Menampilkan halaman konfirmasi
    public function showConfirmationForm(TransferRequest $transfer)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        if ($transfer->to_branch_id != session('active_branch')) {
            abort(403, 'Unauthorized');
        }

        $warehouses = Warehouse::where('branch_id', session('active_branch'))->get();
        return view('transfer::confirm', compact('transfer', 'warehouses'));
    }

    // ✅ Menyimpan hasil konfirmasi dan membuat mutasi
    public function storeConfirmation(Request $request, TransferRequest $transfer)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        if ($transfer->to_branch_id != session('active_branch')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'delivery_proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        DB::transaction(function () use ($request, $transfer) {
            $path = $request->file('delivery_proof')->store('public/transfer_proofs');
            $filename = str_replace('public/', 'storage/', $path);

            $transfer->update([
                'to_warehouse_id' => $request->to_warehouse_id,
                'delivery_proof_path' => $filename,
                'status' => 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            foreach ($transfer->items as $item) {
                $prev = Mutation::where('product_id', $item->product_id)
                    ->where('warehouse_id', $request->to_warehouse_id)
                    ->latest()->first();

                Mutation::create([
                    'reference' => $transfer->reference,
                    'date' => now(),
                    'mutation_type' => 'Transfer',
                    'note' => 'Auto IN from confirmed Transfer #' . $transfer->reference,
                    'warehouse_id' => $request->to_warehouse_id,
                    'product_id' => $item->product_id,
                    'stock_early' => $prev?->stock_last ?? 0,
                    'stock_in' => $item->quantity,
                    'stock_out' => 0,
                    'stock_last' => ($prev?->stock_last ?? 0) + $item->quantity,
                ]);
            }
        });

        toast('Transfer confirmed successfully', 'success');
        return redirect()->route('transfers.index');
    }
    public function destroy($id)
    {
        $transfer = TransferRequest::findOrFail($id);

        // Prevent deletion if stock mutations exist to avoid stock inconsistencies
        if (\Modules\Mutation\Entities\Mutation::where('reference', $transfer->reference)->exists()) {
            return response()->json([
                'message' => 'Cannot delete transfer with existing stock movements. Please cancel via proper reversal flow.'
            ], 422);
        }

        $transfer->delete();

        return response()->json(['message' => 'Transfer deleted successfully']);
    }

}
