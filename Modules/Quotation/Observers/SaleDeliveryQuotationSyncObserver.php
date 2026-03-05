<?php

namespace Modules\Quotation\Observers;

use Modules\Quotation\Services\QuotationStatusService;
use Modules\SaleDelivery\Entities\SaleDelivery;

class SaleDeliveryQuotationSyncObserver
{
    public function created(SaleDelivery $saleDelivery): void
    {
        if (!empty($saleDelivery->quotation_id)) {
            QuotationStatusService::sync((int) $saleDelivery->quotation_id);
        }
    }

    public function deleted(SaleDelivery $saleDelivery): void
    {
        if (!empty($saleDelivery->quotation_id)) {
            QuotationStatusService::sync((int) $saleDelivery->quotation_id);
        }
    }
}