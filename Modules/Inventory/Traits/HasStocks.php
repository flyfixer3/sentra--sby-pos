<?php

namespace Modules\Inventory\Traits;

use Modules\Inventory\Entities\Stock;

trait HasStocks
{
    public function stocks()
    {
        return $this->hasMany(Stock::class, 'product_id');
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->stocks()->sum('qty_available');
    }
}
