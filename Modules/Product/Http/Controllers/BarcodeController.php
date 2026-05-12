<?php

namespace Modules\Product\Http\Controllers;

use App\Support\BranchContext;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Support\LabelPayloadFactory;

class BarcodeController extends Controller
{
    public function __construct(private LabelPayloadFactory $labelPayloadFactory)
    {
    }

    public function printBarcode()
    {
        abort_if(Gate::denies('print_barcodes'), 403);

        return view('product::barcode.index');
    }

    public function printGoodLabel(Product $product)
    {
        abort_if(Gate::denies('print_barcodes'), 403);

        $labels = $this->labelPayloadFactory->buildProductLabels($product, 1, BranchContext::id());

        return \PDF::loadView('product::barcode.print', [
            'labels' => $labels,
            'documentTitle' => 'GOOD Product Label',
        ])->stream('product-label-' . $product->product_code . '.pdf');
    }

    public function printDefectLabel($id)
    {
        abort_if(Gate::denies('print_barcodes'), 403);

        $item = ProductDefectItem::query()
            ->with(['product', 'branch', 'warehouse', 'rack'])
            ->findOrFail($id);

        return \PDF::loadView('product::barcode.print', [
            'labels' => [$this->labelPayloadFactory->buildDefectLabel($item)],
            'documentTitle' => 'DEFECT Product Label',
        ])->stream('defect-label-' . $item->id . '.pdf');
    }

    public function printDamagedLabel($id)
    {
        abort_if(Gate::denies('print_barcodes'), 403);

        $item = ProductDamagedItem::query()
            ->with(['product', 'branch', 'warehouse', 'rack'])
            ->findOrFail($id);

        return \PDF::loadView('product::barcode.print', [
            'labels' => [$this->labelPayloadFactory->buildDamagedLabel($item)],
            'documentTitle' => 'DAMAGED Product Label',
        ])->stream('damaged-label-' . $item->id . '.pdf');
    }
}
