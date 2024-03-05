<?php

namespace Modules\PurchaseOrder\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\PurchaseOrder\Emails\PurchaseOrderMail;
use Modules\PurchaseOrder\Entities\PurchaseOrder;

class SendPurchaseOrderEmailController extends Controller
{
    public function __invoke(PurchaseOrder $purchase_order) {
        try {
            Mail::to($purchase_order->supplier->supplier_email)->send(new PurchaseOrderMail($purchase_order));

            $purchase_order->update([
                'status' => 'Sent'
            ]);

            toast('Sent On "' . $purchase_order->supplier->supplier_email . '"!', 'success');

        } catch (\Exception $exception) {
            Log::error($exception);
            toast('Something Went Wrong!', 'error');
        }

        return back();
    }
}
