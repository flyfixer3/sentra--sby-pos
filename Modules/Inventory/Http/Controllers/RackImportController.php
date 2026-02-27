<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\RackTemplateExport;
use Modules\Inventory\Imports\RacksImport;
use Maatwebsite\Excel\Validators\ValidationException;

class RackImportController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('create_racks'), 403);
        return view('inventory::racks.import');
    }

    public function downloadTemplate()
    {
        abort_if(Gate::denies('create_racks'), 403);
        return Excel::download(new RackTemplateExport(), 'racks_import_template.xlsx');
    }

    public function import(Request $request)
    {
        abort_if(Gate::denies('create_racks'), 403);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            Excel::import(new RacksImport(auth()->id()), $request->file('file'));

            toast('Racks imported successfully!', 'success');
            return redirect()->route('inventory.racks.index');

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
