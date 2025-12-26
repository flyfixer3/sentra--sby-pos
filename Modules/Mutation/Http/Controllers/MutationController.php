<?php

namespace Modules\Mutation\Http\Controllers;

use Modules\Mutation\DataTables\MutationsDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Supplier;
use Modules\People\Entities\Customer;
use App\Models\AccountingAccount;
use App\Helpers\Helper;
use Modules\Product\Entities\Product;
use App\Models\AccountingTransaction;
use Modules\Mutation\Entities\Mutation;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;
use Modules\Sale\Entities\SalePayment;
use Modules\Purchase\Entities\Purchase;
use Carbon\Carbon;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\Purchase\Entities\PurchasePayment;
use Modules\Product\Notifications\NotifyQuantityAlert;
use App\Support\BranchContext;
use Modules\Product\Entities\Warehouse;
use Modules\Inventory\Entities\Stock;

class Debit {
    // Properties
    public $tanggal;
    public $nominal;
    public $keterangan;
}
class Credit {
    // Properties
    public $tanggal;
    public $nominal;
    public $keterangan;
}
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

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_mutations'), 403);

        $request->validate([
            'reference'     => 'required|string|max:255',
            'date'          => 'required|date',
            'note'          => 'nullable|string|max:1000',
            'product_ids'   => 'required|array',
            'quantities'    => 'required|array',
            'mutation_type' => 'required|in:Out,In,Transfer',
        ]);

        DB::transaction(function () use ($request) {

            $active = session('active_branch');

            if ($request->mutation_type == "Out") {

                $warehouseOut = Warehouse::findOrFail($request->warehouse_out_id);

                // optional safety: kalau lagi pilih cabang tertentu, pastikan gudangnya milik cabang itu
                if ($active !== 'all') {
                    abort_unless((int)$warehouseOut->branch_id === (int)$active, 403);
                }

                foreach ($request->product_ids as $key => $id) {
                    $last = Mutation::where('product_id', $id)
                        ->where('warehouse_id', $warehouseOut->id)
                        ->latest()
                        ->first();

                    $_stock_early = $last ? (int)$last->stock_last : 0;
                    $_stock_in = 0;
                    $_stock_out = (int)$request->quantities[$key];
                    $_stock_last = $_stock_early - $_stock_out;

                    Mutation::create([
                        'branch_id'    => (int) $warehouseOut->branch_id, // ✅ penting
                        'reference'    => $request->reference,
                        'date'         => $request->date,
                        'mutation_type'=> $request->mutation_type,
                        'note'         => $request->note,
                        'warehouse_id' => $warehouseOut->id,
                        'product_id'   => (int) $id,
                        'stock_early'  => $_stock_early,
                        'stock_in'     => $_stock_in,
                        'stock_out'    => $_stock_out,
                        'stock_last'   => $_stock_last,
                    ]);
                }

            } elseif ($request->mutation_type == "In") {

                $warehouseIn = Warehouse::findOrFail($request->warehouse_in_id);

                if ($active !== 'all') {
                    abort_unless((int)$warehouseIn->branch_id === (int)$active, 403);
                }

                foreach ($request->product_ids as $key => $id) {
                    $last = Mutation::where('product_id', $id)
                        ->where('warehouse_id', $warehouseIn->id)
                        ->latest()
                        ->first();

                    $_stock_early = $last ? (int)$last->stock_last : 0;
                    $_stock_in = (int)$request->quantities[$key];
                    $_stock_out = 0;
                    $_stock_last = $_stock_early + $_stock_in;

                    Mutation::create([
                        'branch_id'    => (int) $warehouseIn->branch_id, // ✅ penting
                        'reference'    => $request->reference,
                        'date'         => $request->date,
                        'mutation_type'=> $request->mutation_type,
                        'note'         => $request->note,
                        'warehouse_id' => $warehouseIn->id,
                        'product_id'   => (int) $id,
                        'stock_early'  => $_stock_early,
                        'stock_in'     => $_stock_in,
                        'stock_out'    => $_stock_out,
                        'stock_last'   => $_stock_last,
                    ]);
                }

            } elseif ($request->mutation_type == "Transfer") {

                $warehouseOut = Warehouse::findOrFail($request->warehouse_out_id);
                $warehouseIn  = Warehouse::findOrFail($request->warehouse_in_id);

                // kalau kamu mau transfer lintas cabang, hapus 2 baris abort_unless ini
                if ($active !== 'all') {
                    abort_unless((int)$warehouseOut->branch_id === (int)$active, 403);
                    abort_unless((int)$warehouseIn->branch_id === (int)$active, 403);
                }

                foreach ($request->product_ids as $key => $id) {

                    // OUT
                    $lastOut = Mutation::where('product_id', $id)
                        ->where('warehouse_id', $warehouseOut->id)
                        ->latest()
                        ->first();

                    $earlyOut = $lastOut ? (int)$lastOut->stock_last : 0;
                    $qty = (int)$request->quantities[$key];
                    $lastOutStock = $earlyOut - $qty;

                    Mutation::create([
                        'branch_id'    => (int) $warehouseOut->branch_id, // ✅ penting
                        'reference'    => $request->reference,
                        'date'         => $request->date,
                        'mutation_type'=> $request->mutation_type,
                        'note'         => $request->note,
                        'warehouse_id' => $warehouseOut->id,
                        'product_id'   => (int) $id,
                        'stock_early'  => $earlyOut,
                        'stock_in'     => 0,
                        'stock_out'    => $qty,
                        'stock_last'   => $lastOutStock,
                    ]);

                    // IN
                    $lastIn = Mutation::where('product_id', $id)
                        ->where('warehouse_id', $warehouseIn->id)
                        ->latest()
                        ->first();

                    $earlyIn = $lastIn ? (int)$lastIn->stock_last : 0;
                    $lastInStock = $earlyIn + $qty;

                    Mutation::create([
                        'branch_id'    => (int) $warehouseIn->branch_id, // ✅ penting
                        'reference'    => $request->reference,
                        'date'         => $request->date,
                        'mutation_type'=> $request->mutation_type,
                        'note'         => $request->note,
                        'warehouse_id' => $warehouseIn->id,
                        'product_id'   => (int) $id,
                        'stock_early'  => $earlyIn,
                        'stock_in'     => $qty,
                        'stock_out'    => 0,
                        'stock_last'   => $lastInStock, // ✅ fix (sebelumnya pakai $request->stock_in/out)
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

    /**
     * INTERNAL: dipakai modul lain (Adjustment, Sale, Purchase, Transfer)
     * Bikin 1 mutation (In/Out) lalu update Stock table.
     */
    public function applyInOut(
        int $branchId,
        int $warehouseId,
        int $productId,
        string $mutationType, // 'In'|'Out'
        int $qty,
        string $reference,
        string $note,
        string $date // 'Y-m-d' atau datetime
    ): void
    {
        if (!in_array($mutationType, ['In', 'Out'], true)) {
            throw new \RuntimeException("Invalid mutationType: {$mutationType}");
        }
        if ($qty <= 0) {
            throw new \RuntimeException("Qty must be > 0");
        }

        // lock row stock dulu
        $stock = Stock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = Stock::create([
                'branch_id'     => $branchId,
                'warehouse_id'  => $warehouseId,
                'product_id'    => $productId,
                'qty_available' => 0,
                'qty_reserved'  => 0,
                'qty_incoming'  => 0,
                'min_stock'     => 0,
                'note'          => null,
                'created_by'    => auth()->id(),
                'updated_by'    => auth()->id(),
            ]);

            $stock = Stock::withoutGlobalScopes()
                ->where('id', $stock->id)
                ->lockForUpdate()
                ->first();
        }

        $early = (int) $stock->qty_available;

        $in  = $mutationType === 'In' ? $qty : 0;
        $out = $mutationType === 'Out' ? $qty : 0;

        $last = $early + $in - $out;

        if ($last < 0) {
            throw new \RuntimeException("Stock minus tidak diizinkan. Product {$productId}, current {$early}, out {$qty}.");
        }

        // create mutation log
        Mutation::create([
            'branch_id'     => $branchId,
            'warehouse_id'  => $warehouseId,
            'product_id'    => $productId,
            'reference'     => $reference,
            'date'          => $date,
            'mutation_type' => $mutationType, // In/Out
            'note'          => $note,
            'stock_early'   => $early,
            'stock_in'      => $in,
            'stock_out'     => $out,
            'stock_last'    => $last,
        ]);

        // update stock current
        $stock->update([
            'qty_available' => $last,
            'updated_by'    => auth()->id(),
        ]);
    }

    /**
     * INTERNAL: rollback mutation berdasarkan reference (misal ADJ-xxxx).
     * Hati-hati: method ini hanya aman kalau kamu memang mau mengubah history.
     */
    public function rollbackByReference(string $reference, string $notePrefix = 'Adjustment'): void
    {
        $mutations = Mutation::withoutGlobalScopes()
            ->where('reference', $reference)
            ->where('note', 'like', $notePrefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($mutations as $m) {
            $stock = Stock::withoutGlobalScopes()
                ->where('branch_id', (int) $m->branch_id)
                ->where('warehouse_id', (int) $m->warehouse_id)
                ->where('product_id', (int) $m->product_id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = Stock::create([
                    'branch_id'     => (int) $m->branch_id,
                    'warehouse_id'  => (int) $m->warehouse_id,
                    'product_id'    => (int) $m->product_id,
                    'qty_available' => 0,
                    'qty_reserved'  => 0,
                    'qty_incoming'  => 0,
                    'min_stock'     => 0,
                    'note'          => null,
                    'created_by'    => auth()->id(),
                    'updated_by'    => auth()->id(),
                ]);

                $stock = Stock::withoutGlobalScopes()
                    ->where('id', $stock->id)
                    ->lockForUpdate()
                    ->first();
            }

            // balikin ke stock_early mutation ini (asumsi mutation ini yg terakhir untuk item itu)
            $stock->update([
                'qty_available' => (int) $m->stock_early,
                'updated_by'    => auth()->id(),
            ]);

            $m->delete();
        }
    }

    public function destroy(Mutation $mutation) {
        abort_if(Gate::denies('delete_mutations'), 403);

        $mutation->delete();

        toast('Mutation Deleted!', 'warning');

        return redirect()->route('mutations.index');
    }
}
