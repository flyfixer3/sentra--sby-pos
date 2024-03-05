<?php

namespace Modules\PurchaseOrder\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $purchaseOrder;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($purchaseOrder)
    {
        $this->purchaseOrder = $purchaseOrder;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Purchase Order - ' . settings()->company_name)
            ->view('purchase-orders::emails.purchaseOrder', [
                'settings' => settings(),
                'supplier' => $this->purchaseOrder->supplier
            ]);
    }
}
