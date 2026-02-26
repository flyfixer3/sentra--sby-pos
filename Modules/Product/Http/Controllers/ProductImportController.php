<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Product\Exports\ProductTemplateExport;
use Modules\Product\Imports\ProductsImport;
use Maatwebsite\Excel\Validators\ValidationException;

class ProductImportController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('create_products'), 403);
        return view('product::products.import');
    }

    public function downloadTemplate()
    {
        abort_if(Gate::denies('create_products'), 403);
        return Excel::download(new ProductTemplateExport(), 'products_import_template.xlsx');
    }

    public function import(Request $request)
    {
        abort_if(Gate::denies('create_products'), 403);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            Excel::import(new ProductsImport(auth()->id()), $request->file('file'));

            toast('Products imported successfully!', 'success');
            return redirect()->route('products.index');

        } catch (ValidationException $e) {
            $failures = $e->failures();

            // Biar user gampang: tampilkan 1-3 error pertama
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