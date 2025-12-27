<?php

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

use Modules\Transfer\Entities\TransferRequest;
use Modules\Transfer\Entities\TransferRequestItem;
use Modules\Transfer\Entities\PrintLog;
use Modules\Setting\Entities\Setting;

use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;

use Modules\Mutation\Entities\Mutation;
use Modules\Mutation\Http\Controllers\MutationController;

class TransferController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    /**
     * Cabang aktif (session) harus numeric untuk operasional transaksi.
     * Kalau 'all' => tidak boleh create/store (biar data tidak nyasar + mencegah error SQL).
     */
    protected function activeBranch(): mixed
    {
        return session('active_branch');
    }

    protected function activeBranchIdOrFail(): int
    {
        $active = $this->activeBranch();

        if ($active === 'all' || $active === null || $active === '') {
            abort(422, "Please choose a specific branch (not 'All Branch') to create a transfer.");
        }

        return (int) $active;
    }

    public function create()
    {
        abort_if(Gate::denies('create_transfers'), 403);

        $branchId = $this->activeBranchIdOrFail();

        $warehouses = Warehouse::where('branch_id', $branchId)->get();
        $products   = Product::all();

        return view('transfer::create', compact('warehouses', 'products'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_transfers'), 403);

        $branchId = $this->activeBranchIdOrFail();

        $request->validate([
            'reference'         => 'required|string|max:255|unique:transfer_requests,reference',
            'date'              => 'required|date',
            'from_warehouse_id'  => 'required|exists:warehouses,id',
            'to_branch_id'       => 'required|exists:branches,id|different:' . $branchId,
            'note'              => 'nullable|string|max:1000',

            'product_ids'       => 'required|array|min:1',
            'product_ids.*'     => 'required|integer|exists:products,id',
            'quantities'        => 'required|array|min:1',
            'quantities.*'      => 'required|integer|min:1',
        ]);

        // Pastikan qty array sejajar
        if (count($request->product_ids) !== count($request->quantities)) {
            abort(422, 'Product and quantity counts do not match.');
        }

        // Warehouse asal harus milik branch aktif
        $fromWarehouse = Warehouse::findOrFail($request->from_warehouse_id);
        if ((int) $fromWarehouse->branch_id !== $branchId) {
            abort(403, 'From Warehouse must belong to active branch.');
        }

        DB::transaction(function () use ($request, $branchId) {
            $transfer = TransferRequest::create([
                'reference'          => $request->reference,
                'date'               => $request->date,
                'from_warehouse_id'   => $request->from_warehouse_id,
                'to_branch_id'        => $request->to_branch_id,
                'note'               => $request->note,
                'status'             => 'pending',
                'branch_id'           => $branchId,
                'created_by'          => auth()->id(),
            ]);

            foreach ($request->product_ids as $i => $productId) {
                TransferRequestItem::create([
                    'transfer_request_id' => $transfer->id,
                    'product_id'          => (int) $productId,
                    'quantity'            => (int) $request->quantities[$i],
                ]);
            }
        });

        toast('Transfer created successfully', 'success');
        return redirect()->route('transfers.index');
    }

    public function show($id)
    {
        abort_if(Gate::denies('show_transfers'), 403);

        $transfer = TransferRequest::with([
            'fromWarehouse', 'toWarehouse', 'toBranch',
            'creator', 'confirmedBy',
            'items.product',
            'printLogs',
        ])->findOrFail($id);

        return view('transfer::show', compact('transfer'));
    }

    /**
     * PRINT SURAT JALAN:
     * - Print pertama kali => set shipped + mutation OUT dari gudang asal
     * - Reprint => hanya admin/supervisor
     * - Semua print dicatat di PrintLog
     */
    public function printPdf($id)
    {
        abort_if(Gate::denies('print_transfers'), 403);

        $transfer = TransferRequest::with(['items.product', 'fromWarehouse', 'toBranch'])->findOrFail($id);
        $setting  = Setting::first();
        $user     = Auth::user();

        // Pastikan items kebaca
        $transfer->loadMissing('items');

        // Reprint guard (kalau sudah pernah print)
        if ($transfer->printed_at) {
            if (!$user->hasAnyRole(['Super Admin', 'Administrator', 'Supervisor'])) {
                abort(403, 'Only admin/supervisor can reprint.');
            }
        }

        DB::transaction(function () use ($transfer, $user) {
            // Print pertama kali => shipped + OUT stok
            if (!$transfer->printed_at) {

                // Update status + printed mark
                $transfer->update([
                    'printed_at' => now(),
                    'printed_by' => $user->id,
                    'status'     => 'shipped',
                ]);

                // Anti dobel OUT: kalau ternyata mutation OUT sudah ada, jangan bikin lagi
                $alreadyOut = Mutation::withoutGlobalScopes()
                    ->where('reference', $transfer->reference)
                    ->where('note', 'like', 'Transfer OUT%')
                    ->exists();

                if (!$alreadyOut) {
                    foreach ($transfer->items as $item) {
                        $note = "Transfer OUT #{$transfer->reference} | From WH {$transfer->from_warehouse_id}";

                        $this->mutationController->applyInOut(
                            (int) $transfer->branch_id,
                            (int) $transfer->from_warehouse_id,
                            (int) $item->product_id,
                            'Out',
                            (int) $item->quantity,
                            (string) $transfer->reference,
                            $note,
                            (string) $transfer->getRawOriginal('date')
                        );
                    }
                }
            }

            // Log print (SELALU)
            PrintLog::create([
                'user_id'             => $user->id,
                'transfer_request_id' => $transfer->id,
                'printed_at'          => now(),
                'ip_address'          => request()->ip(),
            ]);
        });

        $pdf = Pdf::loadView('transfer::print', compact('transfer', 'setting'))
            ->setPaper('A4', 'portrait');

        return $pdf->download("Surat_Jalan_{$transfer->reference}.pdf");
    }

    /**
     * Halaman konfirmasi penerimaan
     */
    public function showConfirmationForm(TransferRequest $transfer)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        $active = $this->activeBranch();

        // Incoming side: active branch harus sama dengan to_branch_id (kecuali all: biasanya jangan dipakai untuk konfirmasi)
        if ($active === 'all') {
            abort(422, "Please choose a specific branch (not 'All Branch') to confirm incoming transfer.");
        }

        if ((int) $transfer->to_branch_id !== (int) $active) {
            abort(403, 'Unauthorized.');
        }

        if (in_array($transfer->status, ['cancelled'], true)) {
            abort(422, 'Transfer already cancelled.');
        }

        // Idealnya confirm hanya setelah shipped
        if (!in_array($transfer->status, ['shipped'], true)) {
            abort(422, 'Only shipped transfer can be confirmed.');
        }

        $warehouses = Warehouse::where('branch_id', (int) $active)->get();

        return view('transfer::confirm', compact('transfer', 'warehouses'));
    }

    /**
     * Simpan konfirmasi: set confirmed + mutation IN ke gudang tujuan
     */
    public function storeConfirmation(Request $request, TransferRequest $transfer)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        $active = $this->activeBranch();
        if ($active === 'all') {
            abort(422, "Please choose a specific branch (not 'All Branch') to confirm incoming transfer.");
        }

        if ((int) $transfer->to_branch_id !== (int) $active) {
            abort(403, 'Unauthorized.');
        }

        if (in_array($transfer->status, ['cancelled'], true)) {
            abort(422, 'Transfer already cancelled.');
        }

        if (!in_array($transfer->status, ['shipped'], true)) {
            abort(422, 'Only shipped transfer can be confirmed.');
        }

        $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'delivery_proof'  => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // to warehouse harus milik branch tujuan
        $toWarehouse = Warehouse::findOrFail($request->to_warehouse_id);
        if ((int) $toWarehouse->branch_id !== (int) $transfer->to_branch_id) {
            abort(422, 'To Warehouse must belong to destination branch.');
        }

        DB::transaction(function () use ($request, $transfer) {
            $transfer->loadMissing('items');

            $path     = $request->file('delivery_proof')->store('public/transfer_proofs');
            $filename = str_replace('public/', 'storage/', $path);

            $transfer->update([
                'to_warehouse_id'      => (int) $request->to_warehouse_id,
                'delivery_proof_path'  => $filename,
                'status'               => 'confirmed',
                'confirmed_by'         => auth()->id(),
                'confirmed_at'         => now(),
            ]);

            // Anti dobel IN
            $alreadyIn = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer IN%')
                ->exists();

            if (!$alreadyIn) {
                foreach ($transfer->items as $item) {
                    $note = "Transfer IN #{$transfer->reference} | To WH {$request->to_warehouse_id}";

                    $this->mutationController->applyInOut(
                        (int) $transfer->to_branch_id,
                        (int) $request->to_warehouse_id,
                        (int) $item->product_id,
                        'In',
                        (int) $item->quantity,
                        (string) $transfer->reference,
                        $note,
                        (string) now()->toDateString()
                    );
                }
            }
        });

        toast('Transfer confirmed successfully', 'success');
        return redirect()->route('transfers.index');
    }

    public function destroy($id)
    {
        abort_if(Gate::denies('delete_transfers'), 403);

        $transfer = TransferRequest::findOrFail($id);

        // Jangan delete kalau ada mutation
        if (Mutation::where('reference', $transfer->reference)->exists()) {
            return response()->json([
                'message' => 'Cannot delete transfer with existing stock movements. Please cancel via proper reversal flow.'
            ], 422);
        }

        $transfer->delete();
        return response()->json(['message' => 'Transfer deleted successfully']);
    }

    /**
     * Cancel Transfer:
     * - shipped   => IN balik ke asal
     * - confirmed => OUT tujuan + IN asal
     * - log mutation tidak dihapus
     */
    public function cancel(Request $request, TransferRequest $transfer)
    {
        abort_if(Gate::denies('cancel_transfers'), 403);

        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        if ($transfer->status === 'cancelled') {
            abort(422, 'Transfer already cancelled.');
        }

        if (!in_array($transfer->status, ['shipped', 'confirmed'], true)) {
            abort(422, 'Only shipped/confirmed transfer can be cancelled.');
        }

        DB::transaction(function () use ($request, $transfer) {
            $transfer->loadMissing('items');

            // Idempotent guard: cancel mutation sudah ada
            $alreadyCancelled = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer CANCEL%')
                ->exists();

            if ($alreadyCancelled) {
                abort(422, 'Cancel mutation already exists for this transfer.');
            }

            $cancelNote = trim((string) $request->note);

            // CASE shipped: IN balik ke gudang asal
            if ($transfer->status === 'shipped') {
                foreach ($transfer->items as $item) {
                    $note = "Transfer CANCEL IN #{$transfer->reference} | Return to WH {$transfer->from_warehouse_id} | {$cancelNote}";

                    $this->mutationController->applyInOut(
                        (int) $transfer->branch_id,
                        (int) $transfer->from_warehouse_id,
                        (int) $item->product_id,
                        'In',
                        (int) $item->quantity,
                        (string) $transfer->reference,
                        $note,
                        (string) now()->toDateString()
                    );
                }
            }

            // CASE confirmed: OUT tujuan + IN asal
            if ($transfer->status === 'confirmed') {
                if (!$transfer->to_warehouse_id) {
                    abort(422, 'Invalid transfer data: destination warehouse is empty.');
                }

                // OUT tujuan
                foreach ($transfer->items as $item) {
                    $note = "Transfer CANCEL OUT #{$transfer->reference} | Return from WH {$transfer->to_warehouse_id} | {$cancelNote}";

                    $this->mutationController->applyInOut(
                        (int) $transfer->to_branch_id,
                        (int) $transfer->to_warehouse_id,
                        (int) $item->product_id,
                        'Out',
                        (int) $item->quantity,
                        (string) $transfer->reference,
                        $note,
                        (string) now()->toDateString()
                    );
                }

                // IN asal
                foreach ($transfer->items as $item) {
                    $note = "Transfer CANCEL IN #{$transfer->reference} | Return to WH {$transfer->from_warehouse_id} | {$cancelNote}";

                    $this->mutationController->applyInOut(
                        (int) $transfer->branch_id,
                        (int) $transfer->from_warehouse_id,
                        (int) $item->product_id,
                        'In',
                        (int) $item->quantity,
                        (string) $transfer->reference,
                        $note,
                        (string) now()->toDateString()
                    );
                }
            }

            $transfer->update([
                'status'       => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancel_note'  => $cancelNote,
            ]);
        });

        toast('Transfer cancelled successfully', 'warning');
        return redirect()->route('transfers.index');
    }
}
