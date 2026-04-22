<?php

namespace Modules\Crm\Entities;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends BaseModel
{
    use SoftDeletes;

    protected $table = 'crm_leads';
    protected $guarded = [];
    protected $casts = [
        'glass_types' => 'array',
        'conversation_user_ids' => 'array',
        'sales_owner_user_ids' => 'array',
        'scheduled_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(\Modules\People\Entities\Customer::class, 'customer_id'); }
    public function product(): BelongsTo { return $this->belongsTo(\Modules\Product\Entities\Product::class, 'product_id'); }
    public function saleOrder(): BelongsTo { return $this->belongsTo(\Modules\SaleOrder\Entities\SaleOrder::class, 'sale_order_id'); }
    public function leadProducts(): HasMany { return $this->hasMany(LeadProduct::class, 'lead_id'); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_lead_assignees', 'lead_id', 'user_id')
            ->withPivot(['branch_id', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function serviceOrders(): HasMany { return $this->hasMany(ServiceOrder::class, 'lead_id'); }
}
