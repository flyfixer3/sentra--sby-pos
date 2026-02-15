protected $fillable = [
    'branch_id',
    'customer_id',
    'quotation_id',
    'sale_id',
    'warehouse_id',
    'reference',
    'date',
    'status',
    'note',

    // NEW: financial
    'tax_percentage',
    'tax_amount',
    'discount_percentage',
    'discount_amount',
    'shipping_amount',
    'fee_amount',
    'subtotal_amount',
    'total_amount',

    // NEW: deposit
    'deposit_percentage',
    'deposit_amount',
    'deposit_payment_method',
    'deposit_code',

    'created_by',
    'updated_by',
];
