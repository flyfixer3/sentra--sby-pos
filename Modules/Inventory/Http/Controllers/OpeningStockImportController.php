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

            Excel::import(
                new OpeningStockImport(
                    auth()->id(),
                    (string)$request->date,
                    $reference,
                    $note
                ),
                $request->file('file')
            );

            toast('Opening stock imported successfully (via Mutation)!', 'success');
            return redirect()->route('mutations.index');

        } catch (ValidationException $e) {
            $failures = $e->failures();

            $messages = [];
            foreach ($failures as $f) {
                $messages[] = 'Row ' . $f->row() . ': ' . implode(', ', $f->errors());
                if (count($messages) >= 3) break;
            }

            toast('Import failed. ' . implode(' | ', $messages), 'error');
            return redirect()->back()->withInput();

        } catch (\Throwable $e) {
            toast('Import failed: ' . $e->getMessage(), 'error');
            return redirect()->back()->withInput();
        }
    }
}
