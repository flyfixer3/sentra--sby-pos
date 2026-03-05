<?php

namespace Modules\Quotation\Observers;

use Modules\Quotation\Services\QuotationStatusService;
use Modules\SaleOrder\Entities\SaleOrder;

class SaleOrderQuotationSyncObserver
{
    public function created(SaleOrder $saleOrder): void
    {
        if (!empty($saleOrder->quotation_id)) {
            QuotationStatusService::sync((int) $saleOrder->quotation_id);
        }
    }

    public function deleted(SaleOrder $saleOrder): void
    {
        // "deleted" terpanggil untuk soft delete juga
        if (!empty($saleOrder->quotation_id)) {
            QuotationStatusService::sync((int) $saleOrder->quotation_id);
        }
    }
}