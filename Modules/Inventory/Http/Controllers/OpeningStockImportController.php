<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\OpeningStockTemplateExport;
use Modules\Inventory\Imports\OpeningStockImport;
use Maatwebsite\Excel\Validators\ValidationException;

class OpeningStockImportController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('create_mutations'), 403);
        return view('inventory::stocks.import_opening');
    }

    public function downloadTemplate()
    {
        abort_if(Gate::denies('create_mutations'), 403);
        return Excel::download(new OpeningStockTemplateExport(), 'opening_stock_import_template.xlsx');
    }

    public function import(Request $request)
    {
        abort_if(Gate::denies('create_mutations'), 403);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $reference = trim((string)($request->reference ?? ''));
            if ($reference === '') {
                $reference = 'OB-IMPORT-' . now()->format('Ymd-His');
            }

            $noteInput = trim((string)($request->note ?? 'Opening Balance Import'));
            $note = 'AUTO GENERATED: EXCEL | ' . ($noteInput !== '' ? $noteInput : 'Opening Balance Import');

            $importer = new \Modules\Inventory\Imports\OpeningStockImport(
                auth()->id(),
                (string)$request->date,
                $reference,
                $note
            );

            Excel::import($importer, $request->file('file'));

            // ✅ jangan sampai "sukses" tapi 0 row masuk
            if ((int) $importer->getImportedCount() <= 0) {
                toast('Import finished but no rows were imported. Please check: qty_good >= 1, and all codes are valid.', 'error');
                return redirect()->back()->withInput();
            }

            toast('Opening stock imported successfully (via Mutation + Opening HPP)!', 'success');
            return redirect()->route('mutations.index');

        } catch (\Throwable $e) {
            // semua error termasuk "product not found" akan masuk sini, dan pesannya sudah ada Row X:
            toast('Import failed: ' . $e->getMessage(), 'error');
            return redirect()->back()->withInput();
        }
    }
}
