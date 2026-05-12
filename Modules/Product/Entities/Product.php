<?php

namespace Modules\Product\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Notifications\NotifyQuantityAlert;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Modules\Inventory\Entities\StockRack;
use Modules\Inventory\Traits\HasStocks;

class Product extends BaseModel implements HasMedia
{

    use HasStocks,HasFactory, InteractsWithMedia;

    protected $guarded = [];
 
    protected $with = ['media'];

    public function category() {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
    public function accessory() {
        return $this->belongsTo(Accessory::class, 'accessory_code', 'accessory_code');
    }

    public function accessories()
    {
        return $this->belongsToMany(Accessory::class, 'product_accessory')
            ->withTimestamps();
    }

    public function stockRacks(): HasMany
    {
        return $this->hasMany(StockRack::class, 'product_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function registerMediaCollections(): void {
        $this->addMediaCollection('images')
            ->useFallbackUrl('/images/fallback_product_image.png');
    }

    public function registerMediaConversions(Media $media = null): void {
        $this->addMediaConversion('thumb')
            ->width(50)
            ->height(50);
    }

    public function setProductCostAttribute($value) {
        $this->attributes['product_cost'] = normalize_currency($value);
    }

    public function getProductCostAttribute($value) {
        return ($value / 1);
    }

    public function setProductPriceAttribute($value) {
        $this->attributes['product_price'] = normalize_currency($value);
    }

    public function getProductPriceAttribute($value) {
        return ($value / 1);
    }

    public function setProductPriceItemOnlyAttribute($value): void
    {
        $this->attributes['product_price_item_only'] = ($value === null || $value === '')
            ? null
            : normalize_currency($value);
    }

    public function getProductPriceItemOnlyAttribute($value)
    {
        return $value === null ? null : ($value / 1);
    }

    public function setInstallationServicePriceAttribute($value): void
    {
        $this->attributes['installation_service_price'] = ($value === null || $value === '')
            ? null
            : normalize_currency($value);
    }

    public function getInstallationServicePriceAttribute($value)
    {
        return $value === null ? null : ($value / 1);
    }

    public function setProductPricePackageAttribute($value): void
    {
        $this->attributes['product_price_package'] = ($value === null || $value === '')
            ? null
            : normalize_currency($value);
    }

    public function getProductPricePackageAttribute($value)
    {
        return $value === null ? null : ($value / 1);
    }

    //fungsi with trait
    public function getStockOnHandAttribute(): int
    {
        // Jika query sudah pakai withBranchStock(), field agregat langsung tersedia
        if (array_key_exists('stock_on_hand', $this->attributes)) {
            return (int) $this->attributes['stock_on_hand'];
        }

        // Fallback: hitung dinamis (dipakai di halaman show/edit, dll.)
        $branchId = session('active_branch');
        if (!$branchId) return 0;

        // Ganti 'qty_total' menjadi 'qty' bila kolommu bernama 'qty'
        return (int) $this->stockRacks()
            ->where('branch_id', $branchId)
            ->sum('qty_total');
    }
}
