<?php

namespace App\Console\Commands;

use App\Support\LegacyImport\BranchMap;
use App\Support\LegacyImport\ProductCodeParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Entities\Branch;
use Modules\Inventory\Entities\Rack;
use Modules\Mutation\Http\Controllers\MutationController;
use Modules\People\Entities\Customer;
use Modules\People\Entities\Supplier;
use Modules\Product\Entities\Accessory;
use Modules\Product\Entities\Brand;
use Modules\Product\Entities\Category;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Services\HppService;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Entities\PurchaseDetail;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportLegacyExcelCommand extends Command
{
    protected $signature = 'legacy:import-excel
        {surabaya : Path ke file Excel Surabaya}
        {bekasi : Path ke file Excel Bekasi}
        {tangerang : Path ke file Excel Tangerang}
        {pricelist : Path ke file pricelist}
        {--stage=all : all|products|purchases|sales|reconcile}';

    protected $description = 'Import legacy Excel master products, purchases, sales, and stock reconciliation';

    private const BRANCH_PROFILES = [
        'SBY' => [
            'branch_name' => 'Cabang Surabaya',
            'warehouse_name' => 'Workshop Surabaya',
            'warehouse_code' => 'WS-SBY',
            'sales_ref_prefix' => 'SA-SBY',
            'purchase_sheet' => 'X',
            'purchase_start_row' => 5,
            'sales_sheet' => 'PENJUALAN',
            'sales_start_row' => 4,
            'current_stock_sheet' => 'Current Stock',
            'current_stock_start_row' => 3,
            'current_stock_qty_col' => 9,
            'current_stock_alert_col' => 12,
            'sales_cols' => [
                'date' => 1, 'invoice' => 2, 'code' => 3, 'name' => 4, 'qty' => 5,
                'unit_price' => 6, 'discount' => 8, 'net' => 9, 'customer' => 11,
                'phone' => 12, 'email' => 13, 'plate' => 14, 'address' => 15, 'sale_from' => 16,
            ],
            'purchase_cols' => [
                'date' => 1, 'invoice' => 2, 'supplier' => 3, 'code' => 4, 'name' => 5,
                'qty' => 6, 'unit_price' => 7, 'total' => 8, 'check' => 12,
            ],
        ],
        'BKS' => [
            'branch_name' => 'Cabang Bekasi',
            'warehouse_name' => 'Workshop Bekasi',
            'warehouse_code' => 'WS-BKS',
            'sales_ref_prefix' => 'SA-BKS',
            'purchase_sheet' => 'X',
            'purchase_start_row' => 4,
            'sales_sheet' => 'PENJUALAN',
            'sales_start_row' => 4,
            'current_stock_sheet' => 'Current Stock',
            'current_stock_start_row' => 3,
            'current_stock_qty_col' => 12,
            'current_stock_alert_col' => null,
            'sales_cols' => [
                'date' => 1, 'invoice' => 2, 'code' => 3, 'name' => 4, 'qty' => 5,
                'unit_price' => 7, 'discount' => 9, 'net' => 10, 'customer' => 12,
                'phone' => 13, 'email' => 15, 'plate' => 14, 'address' => 16, 'sale_from' => null,
            ],
            'purchase_cols' => [
                'date' => 1, 'invoice' => 2, 'supplier' => 3, 'code' => 4, 'name' => 5,
                'qty' => 8, 'qty_fallback_a' => 6, 'qty_fallback_b' => 7,
                'unit_price' => 9, 'total' => 10, 'check' => 13, 'check_alt' => 16,
            ],
        ],
        'TGR' => [
            'branch_name' => 'Cabang Tangerang',
            'warehouse_name' => 'Workshop Tangerang',
            'warehouse_code' => 'WS-TGR',
            'sales_ref_prefix' => 'SA-TGR',
            'purchase_sheet' => 'X',
            'purchase_start_row' => 3,
            'sales_sheet' => 'PENJUALAN',
            'sales_start_row' => 4,
            'current_stock_sheet' => 'Current Stock',
            'current_stock_start_row' => 2,
            'current_stock_qty_col' => 10,
            'current_stock_alert_col' => 13,
            'sales_cols' => [
                'date' => 1, 'invoice' => 2, 'code' => 3, 'name' => 4, 'qty' => 5,
                'unit_price' => 6, 'discount' => 8, 'net' => 9, 'customer' => 11,
                'phone' => 12, 'email' => 13, 'plate' => 14, 'address' => 15, 'sale_from' => 16,
            ],
            'purchase_cols' => [
                'date' => 1, 'invoice' => 2, 'supplier' => 3, 'code' => 4, 'name' => 5,
                'qty' => 6, 'unit_price' => 7, 'total' => 8, 'check' => 12,
            ],
        ],
    ];

    private const PART_NAMES = [
        'SWR' => 'Kaca Mati Pojok Kanan',
        'SWL' => 'Kaca Mati Pojok Kiri',
        'RVL' => 'Kaca Mati Pintu Belakang Kiri',
        'RVR' => 'Kaca Mati Pintu Belakang Kanan',
        'RDL' => 'Kaca Pintu Belakang Kiri',
        'RDR' => 'Kaca Pintu Belakang Kanan',
        'LFW' => 'Kaca Depan',
        'TRW' => 'Kaca Belakang',
        'FDL' => 'Kaca Pintu Depan Kiri',
        'FDR' => 'Kaca Pintu Depan Kanan',
        'FVL' => 'Kaca Mati Pintu Depan Kiri',
        'FVR' => 'Kaca Mati Pintu Depan Kanan',
        'SFL' => 'Kaca Mati Kanan Swing Depan',
        'SFR' => 'Kaca Mati Kiri Swing Depan',
        'OTH' => 'Others / Jasa / Consumable',
    ];

    private $mutationController;

    private $hppService;

    public function handle(): int
    {
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $stage = strtolower((string) $this->option('stage'));
        if (!in_array($stage, ['all', 'products', 'purchases', 'sales', 'reconcile'], true)) {
            $this->error('Invalid --stage value.');
            return self::FAILURE;
        }

        $this->mutationController = app(MutationController::class);
        $this->hppService = app(HppService::class);

        $sources = [
            'SBY' => $this->argument('surabaya'),
            'BKS' => $this->argument('bekasi'),
            'TGR' => $this->argument('tangerang'),
        ];

        foreach ($sources as $code => $path) {
            if (!is_file($path)) {
                $this->error("File branch {$code} not found: {$path}");
                return self::FAILURE;
            }
        }
        if (!is_file($this->argument('pricelist'))) {
            $this->error('Pricelist file not found.');
            return self::FAILURE;
        }

        $this->ensureMasterData();

        $pricelistMap = $this->loadPricelist($this->argument('pricelist'));
        $latestSalePrices = $this->scanLatestSalePrices($sources);
        $latestPurchaseCosts = $this->scanLatestPurchaseCosts($sources);

        if (in_array($stage, ['all', 'products'], true)) {
            $this->importProducts($sources, $pricelistMap, $latestSalePrices, $latestPurchaseCosts);
        }

        if (in_array($stage, ['all', 'purchases'], true)) {
            $this->importPurchases($sources, $pricelistMap, $latestSalePrices, $latestPurchaseCosts);
        }

        if (in_array($stage, ['all', 'sales'], true)) {
            $this->importSales($sources, $pricelistMap, $latestSalePrices, $latestPurchaseCosts);
        }

        if (in_array($stage, ['all', 'reconcile'], true)) {
            $this->reconcileStocks($sources);
        }

        $this->syncProductLegacyCost($latestPurchaseCosts);

        $this->info('Legacy Excel import finished.');
        return self::SUCCESS;
    }

    private function ensureMasterData(): void
    {
        foreach (ProductCodeParser::BRAND_NAMES as $code => $name) {
            Brand::query()->firstOrCreate(
                ['brand_code' => $code],
                ['brand_name' => $name]
            );
        }

        foreach (ProductCodeParser::ACCESSORY_TOKENS as $token) {
            Accessory::query()->firstOrCreate(
                ['accessory_code' => strtoupper($token)],
                ['accessory_name' => strtoupper($token)]
            );
        }
        Accessory::query()->firstOrCreate(['accessory_code' => '-'], ['accessory_name' => 'No Accessory']);

        foreach (self::PART_NAMES as $code => $name) {
            Category::query()->firstOrCreate(
                ['category_code' => $code],
                ['category_name' => $name]
            );
        }

        foreach (self::BRANCH_PROFILES as $branchCode => $profile) {
            $branch = Branch::query()->firstOrCreate(
                ['name' => $profile['branch_name']],
                ['address' => $profile['branch_name'], 'phone' => null, 'is_active' => true]
            );

            $warehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
                ['warehouse_code' => $profile['warehouse_code']],
                [
                    'warehouse_name' => $profile['warehouse_name'],
                    'branch_id' => $branch->id,
                    'is_main' => true,
                ]
            );

            if ((int) $warehouse->branch_id !== (int) $branch->id || !(bool) $warehouse->is_main) {
                $warehouse->update([
                    'warehouse_name' => $profile['warehouse_name'],
                    'branch_id' => $branch->id,
                    'is_main' => true,
                ]);
            }

            Rack::withoutGlobalScopes()->firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => 'DEFAULT'],
                [
                    'branch_id' => $branch->id,
                    'name' => 'Default Import Rack',
                    'description' => 'Auto created for legacy Excel import',
                ]
            );
        }
    }

    private function loadPricelist(string $path): array
    {
        return $this->withSheet($path, 'Sheet1', function (Worksheet $sheet) {
            $prices = [];
            $highestRow = $sheet->getHighestDataRow();
            for ($row = 3; $row <= $highestRow; $row++) {
                $key = strtoupper(trim((string) $this->cell($sheet, 1, $row)));
                if ($key === '') {
                    continue;
                }
                $price = $this->asInt($this->cell($sheet, 6, $row));
                if ($price > 0) {
                    $prices[$key] = $price;
                }
            }

            return $prices;
        }, []);
    }

    private function scanLatestSalePrices(array $sources): array
    {
        $latest = [];

        foreach ($sources as $branchCode => $path) {
            $profile = self::BRANCH_PROFILES[$branchCode];
            $this->withSheet($path, $profile['sales_sheet'], function (Worksheet $sheet) use (&$latest, $profile) {
                $cols = $profile['sales_cols'];
                $highestRow = $sheet->getHighestDataRow();
                for ($row = $profile['sales_start_row']; $row <= $highestRow; $row++) {
                    $code = strtoupper(trim((string) $this->cell($sheet, $cols['code'], $row)));
                    if ($code === '' || $code === 'KODE') {
                        continue;
                    }

                    $price = $this->asInt($this->cell($sheet, $cols['unit_price'], $row));
                    $date = $this->parseDate($this->cell($sheet, $cols['date'], $row));
                    if ($price <= 0 || !$date) {
                        continue;
                    }

                    if (!isset($latest[$code]) || $date->gt($latest[$code]['date'])) {
                        $latest[$code] = ['price' => $price, 'date' => $date];
                    }
                }
            });
        }

        return array_map(function ($row) {
            return $row['price'];
        }, $latest);
    }

    private function scanLatestPurchaseCosts(array $sources): array
    {
        $latest = [];

        foreach ($sources as $branchCode => $path) {
            $profile = self::BRANCH_PROFILES[$branchCode];
            $this->withSheet($path, $profile['purchase_sheet'], function (Worksheet $sheet) use (&$latest, $profile) {
                $highestRow = $sheet->getHighestDataRow();
                for ($row = $profile['purchase_start_row']; $row <= $highestRow; $row++) {
                    $code = strtoupper(trim((string) $this->cell($sheet, $profile['purchase_cols']['code'], $row)));
                    if ($code === '' || $code === 'KODE') {
                        continue;
                    }

                    $date = $this->parseDate($this->cell($sheet, $profile['purchase_cols']['date'], $row));
                    $price = $this->asInt($this->cell($sheet, $profile['purchase_cols']['unit_price'], $row));
                    if ($price <= 0 || !$date) {
                        continue;
                    }

                    if (!isset($latest[$code]) || $date->gt($latest[$code]['date'])) {
                        $latest[$code] = ['price' => $price, 'date' => $date];
                    }
                }
            });
        }

        return array_map(function ($row) {
            return $row['price'];
        }, $latest);
    }

    private function importProducts(array $sources, array $pricelistMap, array $latestSalePrices, array $latestPurchaseCosts): void
    {
        $this->info('Importing products from Current Stock...');

        foreach ($sources as $branchCode => $path) {
            $profile = self::BRANCH_PROFILES[$branchCode];
            $this->withSheet($path, $profile['current_stock_sheet'], function (Worksheet $sheet) use ($profile, $pricelistMap, $latestSalePrices, $latestPurchaseCosts) {
                $highestRow = $sheet->getHighestDataRow();
                for ($row = $profile['current_stock_start_row']; $row <= $highestRow; $row++) {
                    $productCode = strtoupper(trim((string) $this->cell($sheet, 1, $row)));
                    if ($productCode === '' || in_array($productCode, ['KODE BARANG', 'KODE'], true)) {
                        continue;
                    }

                    $mobileCode = strtoupper(trim((string) $this->cell($sheet, 2, $row)));
                    $productName = trim((string) $this->cell($sheet, 3, $row));
                    $partCode = strtoupper(trim((string) $this->cell($sheet, 4, $row)));
                    $brandCode = strtoupper(trim((string) $this->cell($sheet, 5, $row)));
                    $accessorySummary = trim((string) $this->cell($sheet, 6, $row));
                    $alert = $profile['current_stock_alert_col']
                        ? $this->asInt($this->cell($sheet, $profile['current_stock_alert_col'], $row), -1)
                        : -1;

                    $parsed = ProductCodeParser::parse($productCode, $partCode, $brandCode, $accessorySummary, $mobileCode, $productName);
                    $reviewReasons = [];

                    $category = $this->resolveCategory($parsed['part_code'], $reviewReasons);
                    $brand = $this->resolveBrand($parsed['brand_code'], $parsed['item_type'], $reviewReasons);
                    $accessorySummaryCode = $this->ensureAccessorySummary($parsed['accessory_summary']);
                    $accessoryIds = $this->ensureAccessoryTokens($parsed['accessory_tokens']);

                    $this->appendItemTypeReviewReasons($parsed['item_type'], $reviewReasons);

                    $price = $pricelistMap[$parsed['price_key']] ?? ($latestSalePrices[$productCode] ?? 0);
                    if ($price <= 0) {
                        $reviewReasons[] = 'PRICELIST_NOT_FOUND';
                    }

                    $cost = $latestPurchaseCosts[$productCode] ?? 0;
                    if ($cost <= 0) {
                        $reviewReasons[] = 'PURCHASE_COST_NOT_FOUND';
                    }

                    $product = Product::withoutGlobalScopes()->updateOrCreate(
                        ['product_code' => $productCode],
                        [
                            'branch_id' => null,
                            'brand_id' => $brand ? $brand->id : null,
                            'item_type' => $parsed['item_type'],
                            'category_id' => $category->id,
                            'accessory_code' => $accessorySummaryCode,
                            'product_name' => $productName !== '' ? $productName : $productCode,
                            'product_barcode_symbology' => 'C128',
                            'product_cost' => $cost,
                            'product_price' => $price,
                            'product_unit' => $this->resolveProductUnit($parsed['item_type']),
                            'product_stock_alert' => $alert >= 0 ? $alert : -1,
                            'product_order_tax' => null,
                            'product_tax_type' => null,
                            'product_note' => $this->mergeNotes(null, $reviewReasons),
                            'needs_review' => !empty($reviewReasons),
                        ]
                    );

                    if (!empty($accessoryIds)) {
                        $product->accessories()->syncWithoutDetaching($accessoryIds);
                    }
                }
            });
        }
    }

    private function importPurchases(array $sources, array $pricelistMap, array $latestSalePrices, array $latestPurchaseCosts): void
    {
        $this->info('Importing purchases from sheet X...');
        $cutoff = now()->copy()->subDays(60)->startOfDay();

        foreach ($sources as $branchCode => $path) {
            $profile = self::BRANCH_PROFILES[$branchCode];
            $branch = $this->resolveBranch($branchCode);
            $warehouse = $this->resolveMainWarehouse($branchCode);
            $rack = $this->resolveDefaultRack($warehouse->id);

            $rowsByInvoice = $this->withSheet($path, $profile['purchase_sheet'], function (Worksheet $sheet) use ($profile) {
                $rowsByInvoice = [];
                $carry = ['date' => null, 'invoice' => null, 'supplier' => null];
                $highestRow = $sheet->getHighestDataRow();
                for ($row = $profile['purchase_start_row']; $row <= $highestRow; $row++) {
                    $code = strtoupper(trim((string) $this->cell($sheet, $profile['purchase_cols']['code'], $row)));
                    if ($code === '' || $code === 'KODE') {
                        continue;
                    }

                    $invoice = trim((string) $this->cell($sheet, $profile['purchase_cols']['invoice'], $row));
                    $supplier = trim((string) $this->cell($sheet, $profile['purchase_cols']['supplier'], $row));
                    $date = $this->parseDate($this->cell($sheet, $profile['purchase_cols']['date'], $row));

                    if ($invoice !== '') {
                        $carry['invoice'] = $invoice;
                    }
                    if ($supplier !== '') {
                        $carry['supplier'] = $supplier;
                    }
                    if ($date) {
                        $carry['date'] = $date;
                    }

                    if (!$carry['date'] || !$carry['invoice'] || !$carry['supplier']) {
                        continue;
                    }
                    if ($this->isVoidMarker($carry['invoice'], $carry['supplier'], $code)) {
                        continue;
                    }

                    $qty = $this->resolvePurchaseQty($sheet, $row, $profile['purchase_cols']);
                    $unitPrice = $this->asInt($this->cell($sheet, $profile['purchase_cols']['unit_price'], $row));
                    $lineTotal = $this->asInt($this->cell($sheet, $profile['purchase_cols']['total'], $row));
                    if ($qty <= 0) {
                        continue;
                    }

                    if ($unitPrice <= 0 && $lineTotal > 0) {
                        $unitPrice = (int) floor($lineTotal / max(1, $qty));
                    }

                    $checkText = strtoupper(trim((string) $this->cell($sheet, $profile['purchase_cols']['check'], $row)));
                    if (!empty($profile['purchase_cols']['check_alt'])) {
                        $checkText .= ' ' . strtoupper(trim((string) $this->cell($sheet, $profile['purchase_cols']['check_alt'], $row)));
                    }

                    $rowsByInvoice[$carry['invoice']]['date'] = $carry['date'];
                    $rowsByInvoice[$carry['invoice']]['invoice'] = $carry['invoice'];
                    $rowsByInvoice[$carry['invoice']]['supplier'] = $carry['supplier'];
                    $line = [
                        'product_code' => $code,
                        'product_name' => trim((string) $this->cell($sheet, $profile['purchase_cols']['name'], $row)),
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal > 0 ? $lineTotal : ($qty * $unitPrice),
                    ];
                    $line['is_internal_transfer'] = $this->isInternalTransferCandidate($line['qty'], $line['unit_price'], $line['line_total']);

                    $rowsByInvoice[$carry['invoice']]['lines'][] = $line;
                    $rowsByInvoice[$carry['invoice']]['check'][] = $checkText;
                }

                return $rowsByInvoice;
            }, []);

            foreach ($rowsByInvoice as $invoice => $group) {
                $exists = Purchase::withoutGlobalScopes()
                    ->where('branch_id', $branch->id)
                    ->where('reference_supplier', $invoice)
                    ->whereDate('date', $group['date']->toDateString())
                    ->exists();
                if ($exists) {
                    continue;
                }

                $commercialLines = array_values(array_filter($group['lines'], function (array $line) {
                    return !$line['is_internal_transfer'];
                }));
                $transferLines = array_values(array_filter($group['lines'], function (array $line) {
                    return $line['is_internal_transfer'];
                }));

                foreach ($transferLines as $line) {
                    $product = $this->findOrCreateProductFromTransaction(
                        $line['product_code'],
                        $line['product_name'],
                        $pricelistMap,
                        $latestSalePrices,
                        $latestPurchaseCosts
                    );

                    $this->recordLegacyInternalMutation(
                        $branch->id,
                        $warehouse->id,
                        $rack->id,
                        $product,
                        'In',
                        $line['qty'],
                        $invoice,
                        $group['date']->toDateString(),
                        'X'
                    );
                }

                if (empty($commercialLines)) {
                    continue;
                }

                $supplier = Supplier::withoutGlobalScopes()->firstOrCreate(
                    ['supplier_name' => $group['supplier'], 'branch_id' => $branch->id],
                    ['supplier_email' => null, 'supplier_phone' => null, 'city' => null, 'country' => null, 'address' => null]
                );

                $totalAmount = array_sum(array_column($commercialLines, 'line_total'));
                $totalQty = array_sum(array_column($commercialLines, 'qty'));
                $isRecent = $group['date']->copy()->startOfDay()->gte($cutoff);
                $isCheckedOk = collect($group['check'])->contains(function ($text) {
                    return strpos((string) $text, 'OK') !== false;
                });
                $isPaid = !$isRecent || $isCheckedOk;
                $dueDays = $this->resolveSupplierDueDays($group['supplier']);

                $purchase = Purchase::create([
                    'reference' => 'AUTO',
                    'reference_supplier' => $invoice,
                    'date' => $group['date']->toDateString(),
                    'due_date' => $dueDays,
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->supplier_name,
                    'tax_percentage' => 0,
                    'tax_amount' => 0,
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                    'shipping_amount' => 0,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $isPaid ? $totalAmount : 0,
                    'due_amount' => $isPaid ? 0 : $totalAmount,
                    'status' => 'Completed',
                    'payment_status' => $isPaid ? 'Paid' : 'Unpaid',
                    'payment_method' => $isPaid ? 'Bank Transfer' : 'Other',
                    'note' => $isPaid ? 'LEGACY IMPORT X' : 'LEGACY IMPORT X | REVIEW MANUAL: payment status',
                    'branch_id' => $branch->id,
                    'warehouse_id' => $warehouse->id,
                    'total_quantity' => $totalQty,
                ]);

                foreach ($commercialLines as $line) {
                    $product = $this->findOrCreateProductFromTransaction(
                        $line['product_code'],
                        $line['product_name'],
                        $pricelistMap,
                        $latestSalePrices,
                        $latestPurchaseCosts
                    );

                    PurchaseDetail::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'product_name' => $line['product_name'] !== '' ? $line['product_name'] : $product->product_name,
                        'product_code' => $product->product_code,
                        'quantity' => $line['qty'],
                        'price' => $line['unit_price'],
                        'unit_price' => $line['unit_price'],
                        'sub_total' => $line['line_total'],
                        'product_discount_amount' => 0,
                        'product_discount_type' => 'fixed',
                        'product_tax_amount' => 0,
                        'warehouse_id' => $warehouse->id,
                    ]);

                    if ($this->shouldTrackInventory($product)) {
                        $this->mutationController->applyInOut(
                            $branch->id,
                            $warehouse->id,
                            $product->id,
                            'In',
                            $line['qty'],
                            $purchase->reference,
                            'LEGACY IMPORT PURCHASE',
                            $purchase->date,
                            $rack->id,
                            'good',
                            'summary'
                        );

                        $this->applyLegacyBranchHpp(
                            $branch->id,
                            $product->id,
                            $line['qty'],
                            $line['unit_price'],
                            $purchase->date,
                            $purchase->id
                        );
                    }
                }
            }
        }
    }

    private function importSales(array $sources, array $pricelistMap, array $latestSalePrices, array $latestPurchaseCosts): void
    {
        $this->info('Importing sales from PENJUALAN...');

        foreach ($sources as $branchCode => $path) {
            $profile = self::BRANCH_PROFILES[$branchCode];
            $branch = $this->resolveBranch($branchCode);
            $warehouse = $this->resolveMainWarehouse($branchCode);
            $rack = $this->resolveDefaultRack($warehouse->id);

            $sales = $this->withSheet($path, $profile['sales_sheet'], function (Worksheet $sheet) use ($profile) {
                $cols = $profile['sales_cols'];
                $highestRow = $sheet->getHighestDataRow();
                $sales = [];
                for ($row = $profile['sales_start_row']; $row <= $highestRow; $row++) {
                    $invoice = trim((string) $this->cell($sheet, $cols['invoice'], $row));
                    $code = strtoupper(trim((string) $this->cell($sheet, $cols['code'], $row)));
                    if ($invoice === '' || $code === '' || $code === 'KODE') {
                        continue;
                    }
                    if ($this->isVoidMarker($invoice, $code, $this->cell($sheet, $cols['name'], $row))) {
                        continue;
                    }

                    $date = $this->parseDate($this->cell($sheet, $cols['date'], $row));
                    if (!$date) {
                        continue;
                    }

                    $qty = $this->asInt($this->cell($sheet, $cols['qty'], $row));
                    $unitPrice = $this->asInt($this->cell($sheet, $cols['unit_price'], $row));
                    $discount = $this->asInt($this->cell($sheet, $cols['discount'], $row));
                    $net = $this->asInt($this->cell($sheet, $cols['net'], $row));
                    if ($qty <= 0) {
                        continue;
                    }
                    if ($net <= 0 && $unitPrice > 0) {
                        $net = ($qty * $unitPrice) - $discount;
                    }

                    $line = [
                        'product_code' => $code,
                        'product_name' => trim((string) $this->cell($sheet, $cols['name'], $row)),
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'discount' => max(0, $discount),
                        'net' => max(0, $net),
                    ];
                    $line['is_internal_transfer'] = $this->isInternalTransferCandidate($line['qty'], $line['unit_price'], $line['net']);

                    $sales[$invoice]['date'] = $date;
                    $sales[$invoice]['invoice'] = $invoice;
                    $sales[$invoice]['customer_name'] = trim((string) $this->cell($sheet, $cols['customer'], $row)) ?: 'Walk-in';
                    $sales[$invoice]['customer_phone'] = $cols['phone'] ? trim((string) $this->cell($sheet, $cols['phone'], $row)) : null;
                    $sales[$invoice]['customer_email'] = $cols['email'] ? trim((string) $this->cell($sheet, $cols['email'], $row)) : null;
                    $sales[$invoice]['license_number'] = $cols['plate'] ? trim((string) $this->cell($sheet, $cols['plate'], $row)) : null;
                    $sales[$invoice]['address'] = $cols['address'] ? trim((string) $this->cell($sheet, $cols['address'], $row)) : null;
                    $sales[$invoice]['sale_from'] = $cols['sale_from'] ? trim((string) $this->cell($sheet, $cols['sale_from'], $row)) : 'Other';
                    $sales[$invoice]['lines'][] = $line;
                }

                return $sales;
            }, []);

            foreach ($sales as $invoice => $group) {
                $reference = $profile['sales_ref_prefix'] . strtoupper($invoice);
                $exists = Sale::withoutGlobalScopes()
                    ->where('reference', $reference)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $commercialLines = array_values(array_filter($group['lines'], function (array $line) {
                    return !$line['is_internal_transfer'];
                }));
                $transferLines = array_values(array_filter($group['lines'], function (array $line) {
                    return $line['is_internal_transfer'];
                }));

                foreach ($transferLines as $line) {
                    $product = $this->findOrCreateProductFromTransaction(
                        $line['product_code'],
                        $line['product_name'],
                        $pricelistMap,
                        $latestSalePrices,
                        $latestPurchaseCosts
                    );

                    $this->recordLegacyInternalMutation(
                        $branch->id,
                        $warehouse->id,
                        $rack->id,
                        $product,
                        'Out',
                        $line['qty'],
                        $invoice,
                        $group['date']->toDateString(),
                        'PENJUALAN'
                    );
                }

                if (empty($commercialLines)) {
                    continue;
                }

                $customer = Customer::withoutGlobalScopes()->firstOrCreate(
                    ['customer_name' => $group['customer_name'], 'branch_id' => $branch->id],
                    [
                        'customer_phone' => $group['customer_phone'] ?: null,
                        'customer_email' => $group['customer_email'] ?: null,
                        'city' => null,
                        'country' => null,
                        'address' => $group['address'] ?: null,
                    ]
                );

                $totalAmount = array_sum(array_column($commercialLines, 'net'));
                $totalQty = array_sum(array_column($commercialLines, 'qty'));

                $saleData = [
                    'reference' => $reference,
                    'date' => $group['date']->toDateString(),
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->customer_name,
                    'tax_percentage' => 0,
                    'tax_amount' => 0,
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                    'shipping_amount' => 0,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $totalAmount,
                    'due_amount' => 0,
                    'payment_status' => 'Paid',
                    'payment_method' => 'Other',
                    'note' => 'LEGACY IMPORT PENJUALAN',
                ];

                if (Schema::hasColumn('sales', 'branch_id')) {
                    $saleData['branch_id'] = $branch->id;
                }
                if (Schema::hasColumn('sales', 'warehouse_id')) {
                    $saleData['warehouse_id'] = $warehouse->id;
                }
                if (Schema::hasColumn('sales', 'rack_id')) {
                    $saleData['rack_id'] = $rack->id;
                }
                if (Schema::hasColumn('sales', 'license_number')) {
                    $saleData['license_number'] = $group['license_number'] ?: '-';
                }
                if (Schema::hasColumn('sales', 'sale_from')) {
                    $saleData['sale_from'] = $group['sale_from'] ?: 'Other';
                }
                if (Schema::hasColumn('sales', 'automated')) {
                    $saleData['automated'] = false;
                }
                if (Schema::hasColumn('sales', 'status')) {
                    $saleData['status'] = 'Completed';
                }
                if (Schema::hasColumn('sales', 'total_quantity')) {
                    $saleData['total_quantity'] = $totalQty;
                }
                if (Schema::hasColumn('sales', 'fee_amount')) {
                    $saleData['fee_amount'] = 0;
                }

                $sale = Sale::create($saleData);

                foreach ($commercialLines as $line) {
                    $product = $this->findOrCreateProductFromTransaction(
                        $line['product_code'],
                        $line['product_name'],
                        $pricelistMap,
                        $latestSalePrices,
                        $latestPurchaseCosts
                    );

                    $tracksInventory = $this->shouldTrackInventory($product);
                    if ($tracksInventory) {
                        $this->ensureSufficientStockForLegacySale($branch->id, $warehouse->id, $rack->id, $product, $line['qty'], $sale->date);
                    }

                    $hpp = $tracksInventory
                        ? $this->resolveLegacySaleCost($product, (int) $branch->id, (string) $sale->date, $latestPurchaseCosts)
                        : 0;
                    $detail = [
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'product_name' => $line['product_name'] !== '' ? $line['product_name'] : $product->product_name,
                        'product_code' => $product->product_code,
                        'quantity' => $line['qty'],
                        'price' => $line['qty'] > 0 ? (int) floor($line['net'] / $line['qty']) : $line['unit_price'],
                        'unit_price' => $line['unit_price'],
                        'sub_total' => $line['net'],
                        'product_discount_amount' => $line['discount'],
                        'product_discount_type' => 'fixed',
                        'product_tax_amount' => 0,
                        'product_cost' => $hpp,
                    ];
                    if (Schema::hasColumn('sale_details', 'branch_id')) {
                        $detail['branch_id'] = $branch->id;
                    }
                    if (Schema::hasColumn('sale_details', 'warehouse_id')) {
                        $detail['warehouse_id'] = $warehouse->id;
                    }
                    SaleDetails::create($detail);

                    if ($tracksInventory) {
                        $this->applyLegacyOutgoingMutation(
                            $branch->id,
                            $warehouse->id,
                            $rack->id,
                            $product,
                            $line['qty'],
                            $sale->reference,
                            'LEGACY IMPORT SALE',
                            $sale->date
                        );
                    }
                }
            }
        }
    }

    private function reconcileStocks(array $sources): void
    {
        $this->info('Reconciling stocks against Current Stock...');

        foreach ($sources as $branchCode => $path) {
            $profile = self::BRANCH_PROFILES[$branchCode];
            $branch = $this->resolveBranch($branchCode);
            $warehouse = $this->resolveMainWarehouse($branchCode);
            $rack = $this->resolveDefaultRack($warehouse->id);

            $this->withSheet($path, $profile['current_stock_sheet'], function (Worksheet $sheet) use ($profile, $branch, $warehouse, $rack, $branchCode) {
                $highestRow = $sheet->getHighestDataRow();
                for ($row = $profile['current_stock_start_row']; $row <= $highestRow; $row++) {
                    $productCode = strtoupper(trim((string) $this->cell($sheet, 1, $row)));
                    if ($productCode === '' || in_array($productCode, ['KODE BARANG', 'KODE'], true)) {
                        continue;
                    }

                    $expected = $this->asInt($this->cell($sheet, $profile['current_stock_qty_col'], $row));
                    $product = Product::withoutGlobalScopes()->where('product_code', $productCode)->first();
                    if (!$product) {
                        continue;
                    }
                    if (!$this->shouldTrackInventory($product)) {
                        continue;
                    }

                    $actual = (int) DB::table('stocks')
                        ->where('branch_id', $branch->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_id', $product->id)
                        ->value('qty_total');

                    if ($expected < 0) {
                        $this->markProductReview($product, 'NEGATIVE_CURRENT_STOCK_IN_EXCEL');
                        $expected = 0;
                    }

                    $delta = $expected - $actual;
                    if ($delta === 0) {
                        continue;
                    }

                    $type = $delta > 0 ? 'In' : 'Out';
                    $qty = abs($delta);
                    if ($qty <= 0) {
                        continue;
                    }

                    if ($type === 'Out') {
                        $available = max(0, $actual);
                        if ($available < $qty) {
                            $this->markProductReview($product, 'RECONCILIATION_NEGATIVE_DELTA_CLAMPED');
                            $qty = $available;
                        }
                        if ($qty <= 0) {
                            continue;
                        }
                    }

                    $this->mutationController->applyInOut(
                        $branch->id,
                        $warehouse->id,
                        $product->id,
                        $type,
                        $qty,
                        'RCN-' . $branchCode . '-' . now()->format('Ymd'),
                        'IMPORT RECONCILIATION',
                        now()->toDateString(),
                        $rack->id,
                        'good',
                        'summary'
                    );
                }
            });
        }
    }

    private function syncProductLegacyCost(array $latestPurchaseCosts): void
    {
        $this->info('Syncing products.product_cost from latest legacy purchase cost...');

        foreach (Product::withoutGlobalScopes()->get() as $product) {
            $globalCost = (int) ($latestPurchaseCosts[$product->product_code] ?? 0);
            if ($globalCost > 0) {
                $product->update([
                    'product_cost' => $globalCost,
                ]);
            }
        }
    }

    private function findOrCreateProductFromTransaction(
        string $productCode,
        string $productName,
        array $pricelistMap,
        array $latestSalePrices,
        array $latestPurchaseCosts
    ): Product {
        $productCode = strtoupper(trim($productCode));
        $existing = Product::withoutGlobalScopes()->where('product_code', $productCode)->first();
        if ($existing) {
            return $existing;
        }

        $parsed = ProductCodeParser::parse($productCode, null, null, null, null, $productName);
        $reasons = ['AUTO_CREATED_FROM_TRANSACTION'];

        $category = $this->resolveCategory($parsed['part_code'], $reasons);
        $brand = $this->resolveBrand($parsed['brand_code'], $parsed['item_type'], $reasons);
        $summary = $this->ensureAccessorySummary($parsed['accessory_summary']);
        $accessoryIds = $this->ensureAccessoryTokens($parsed['accessory_tokens']);
        $this->appendItemTypeReviewReasons($parsed['item_type'], $reasons);

        $price = $pricelistMap[$parsed['price_key']] ?? ($latestSalePrices[$productCode] ?? 0);
        if ($price <= 0) {
            $reasons[] = 'PRICE_NOT_FOUND';
        }

        $cost = $latestPurchaseCosts[$productCode] ?? 0;
        if ($cost <= 0) {
            $reasons[] = 'COST_NOT_FOUND';
        }

        $product = Product::withoutGlobalScopes()->create([
            'branch_id' => null,
            'brand_id' => $brand ? $brand->id : null,
            'item_type' => $parsed['item_type'],
            'category_id' => $category->id,
            'accessory_code' => $summary,
            'product_name' => $productName !== '' ? $productName : $productCode,
            'product_code' => $productCode,
            'product_barcode_symbology' => 'C128',
            'product_cost' => $cost,
            'product_price' => $price,
            'product_unit' => $this->resolveProductUnit($parsed['item_type']),
            'product_stock_alert' => -1,
            'product_order_tax' => null,
            'product_tax_type' => null,
            'product_note' => $this->mergeNotes(null, $reasons),
            'needs_review' => true,
        ]);

        if (!empty($accessoryIds)) {
            $product->accessories()->syncWithoutDetaching($accessoryIds);
        }

        return $product;
    }

    private function ensureSufficientStockForLegacySale(int $branchId, int $warehouseId, int $rackId, Product $product, int $qty, string $date): void
    {
        $available = (int) DB::table('stocks')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $product->id)
            ->value('qty_total');

        if ($available >= $qty) {
            return;
        }

        $shortage = $qty - max(0, $available);
        if ($shortage <= 0) {
            return;
        }

        $this->markProductReview($product, 'AUTO_OPENING_BALANCE_FOR_HISTORICAL_SALE');

        $this->mutationController->applyInOut(
            $branchId,
            $warehouseId,
            $product->id,
            'In',
            $shortage,
            'LEGACY-OPEN-' . $branchId . '-' . $product->id,
            'AUTO GENERATED: LEGACY IMPORT OPENING BALANCE',
            $date,
            $rackId,
            'good',
            'summary'
        );
    }

    private function applyLegacyOutgoingMutation(
        int $branchId,
        int $warehouseId,
        int $rackId,
        Product $product,
        int $qty,
        string $reference,
        string $note,
        string $date
    ): void {
        $this->ensureSufficientStockForLegacySale($branchId, $warehouseId, $rackId, $product, $qty, $date);

        try {
            $this->mutationController->applyInOut(
                $branchId,
                $warehouseId,
                $product->id,
                'Out',
                $qty,
                $reference,
                $note,
                $date,
                $rackId,
                'good',
                'summary'
            );
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Stock minus') === false) {
                throw $e;
            }

            $this->ensureSufficientStockForLegacySale($branchId, $warehouseId, $rackId, $product, $qty, $date);
            $this->mutationController->applyInOut(
                $branchId,
                $warehouseId,
                $product->id,
                'Out',
                $qty,
                $reference,
                $note,
                $date,
                $rackId,
                'good',
                'summary'
            );
        }
    }

    private function recordLegacyInternalMutation(
        int $branchId,
        int $warehouseId,
        int $rackId,
        Product $product,
        string $type,
        int $qty,
        string $legacyInvoice,
        string $date,
        string $sourceSheet
    ): void {
        if ($qty <= 0 || !$this->shouldTrackInventory($product)) {
            return;
        }

        $reference = sprintf(
            'INT-%s-%s-%s',
            strtoupper($sourceSheet) === 'X' ? 'IN' : 'OUT',
            $branchId,
            strtoupper(trim($legacyInvoice))
        );
        $note = sprintf('LEGACY INTERNAL TRANSFER FROM %s', strtoupper($sourceSheet));

        if ($type === 'Out') {
            $this->applyLegacyOutgoingMutation(
                $branchId,
                $warehouseId,
                $rackId,
                $product,
                $qty,
                $reference,
                $note,
                $date
            );
            return;
        }

        if ($this->legacyMutationExists($branchId, $warehouseId, $product->id, $reference, $date, $type, $note)) {
            return;
        }

        $this->mutationController->applyInOut(
            $branchId,
            $warehouseId,
            $product->id,
            $type,
            $qty,
            $reference,
            $note,
            $date,
            $rackId,
            'good',
            'summary'
        );
    }

    private function shouldTrackInventory(Product $product): bool
    {
        return !in_array((string) $product->item_type, ['service', 'film'], true);
    }

    private function resolveLegacySaleCost(Product $product, int $branchId, string $date, array $latestPurchaseCosts): int
    {
        $globalCost = (int) ($latestPurchaseCosts[$product->product_code] ?? 0);
        if ($globalCost > 0) {
            return $globalCost;
        }

        $productCost = (int) round((float) ($product->product_cost ?? 0), 0);
        if ($productCost > 0) {
            return $productCost;
        }

        $branchHpp = (int) round(max(0.0, $this->hppService->getHppAsOf($branchId, $product->id, $date)), 0);
        if ($branchHpp > 0) {
            return $branchHpp;
        }

        $this->markProductReview($product, 'COST_NOT_FOUND');

        return 0;
    }

    private function applyLegacyBranchHpp(
        int $branchId,
        int $productId,
        int $qty,
        int $unitPrice,
        string $date,
        int $purchaseId
    ): void {
        if ($qty <= 0 || $unitPrice <= 0) {
            return;
        }

        $this->hppService->applyIncoming(
            $branchId,
            $productId,
            $qty,
            $unitPrice,
            0,
            0,
            0,
            $date,
            'legacy_purchase_import',
            $purchaseId
        );
    }

    private function resolveCategory(?string $partCode, array &$reviewReasons): Category
    {
        $partCode = strtoupper(trim((string) $partCode));
        if ($partCode === '') {
            $partCode = 'OTH';
            $reviewReasons[] = 'PART_FALLBACK_OTH';
        }

        return Category::query()->firstOrCreate(
            ['category_code' => $partCode],
            ['category_name' => self::PART_NAMES[$partCode] ?? $partCode]
        );
    }

    private function resolveBrand(?string $brandCode, string $itemType, array &$reviewReasons): ?Brand
    {
        if ($itemType !== 'glass') {
            return null;
        }

        $brandCode = strtoupper(trim((string) $brandCode));
        if ($brandCode === '') {
            $reviewReasons[] = 'BRAND_NOT_FOUND';
            return null;
        }

        if (!array_key_exists($brandCode, ProductCodeParser::BRAND_NAMES)) {
            $reviewReasons[] = 'UNKNOWN_GLASS_BRAND';
            return null;
        }

        return Brand::query()->firstOrCreate(
            ['brand_code' => $brandCode],
            ['brand_name' => ProductCodeParser::BRAND_NAMES[$brandCode] ?? $brandCode]
        );
    }

    private function resolveProductUnit(string $itemType): string
    {
        if ($itemType === 'film') {
            return 'PC';
        }

        if ($itemType === 'service') {
            return 'JOB';
        }

        return 'PC';
    }

    private function appendItemTypeReviewReasons(string $itemType, array &$reviewReasons): void
    {
        if ($itemType === 'film') {
            $reviewReasons[] = 'FILM_STOCK_REVIEW';
        }
    }

    private function ensureAccessorySummary(?string $summary): string
    {
        $summary = strtoupper(trim((string) $summary));
        $summary = $summary !== '' ? $summary : '-';

        Accessory::query()->firstOrCreate(
            ['accessory_code' => $summary],
            ['accessory_name' => $summary]
        );

        return $summary;
    }

    private function ensureAccessoryTokens(array $tokens): array
    {
        $ids = [];
        foreach ($tokens as $token) {
            $token = strtoupper(trim((string) $token));
            if ($token === '') {
                continue;
            }

            $accessory = Accessory::query()->firstOrCreate(
                ['accessory_code' => $token],
                ['accessory_name' => $token]
            );
            $ids[] = $accessory->id;
        }

        return array_values(array_unique($ids));
    }

    private function resolveBranch(string $branchCode): Branch
    {
        return Branch::withoutGlobalScopes()
            ->where('name', self::BRANCH_PROFILES[$branchCode]['branch_name'])
            ->firstOrFail();
    }

    private function resolveMainWarehouse(string $branchCode): Warehouse
    {
        return Warehouse::withoutGlobalScopes()
            ->where('warehouse_code', self::BRANCH_PROFILES[$branchCode]['warehouse_code'])
            ->firstOrFail();
    }

    private function resolveDefaultRack(int $warehouseId): Rack
    {
        return Rack::withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->where('code', 'DEFAULT')
            ->firstOrFail();
    }

    private function resolvePurchaseQty(Worksheet $sheet, int $row, array $cols): int
    {
        $qty = $this->asInt($this->cell($sheet, $cols['qty'], $row));
        if ($qty > 0) {
            return $qty;
        }

        foreach (['qty_fallback_a', 'qty_fallback_b'] as $key) {
            if (!empty($cols[$key])) {
                $qty = $this->asInt($this->cell($sheet, $cols[$key], $row));
                if ($qty > 0) {
                    return $qty;
                }
            }
        }

        return 0;
    }

    private function isInternalTransferCandidate(int $qty, int $unitPrice, int $lineTotal): bool
    {
        return $qty > 0 && $unitPrice <= 0 && $lineTotal <= 0;
    }

    private function resolveSupplierDueDays(string $supplierName): int
    {
        $name = strtoupper(trim($supplierName));

        if (in_array($name, ['BGI', 'RJG', 'PAN', 'HNR'], true)) {
            return 60;
        }

        if (in_array($name, ['MJD', 'PJM', 'SG', 'VK', 'PJB'], true)) {
            return 30;
        }

        return 0;
    }

    private function legacyMutationExists(
        int $branchId,
        int $warehouseId,
        int $productId,
        string $reference,
        string $date,
        string $type,
        string $note
    ): bool {
        return DB::table('mutations')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('reference', $reference)
            ->whereDate('date', $date)
            ->where('mutation_type', ucfirst(strtolower($type)))
            ->where('note', $note)
            ->exists();
    }

    private function isVoidMarker(...$values): bool
    {
        foreach ($values as $value) {
            $text = strtoupper(trim((string) $value));
            if ($text !== '' && strpos($text, 'VOID') !== false) {
                return true;
            }
        }

        return false;
    }

    private function markProductReview(Product $product, string $reason): void
    {
        $note = $this->mergeNotes($product->product_note, [$reason]);
        $product->update([
            'needs_review' => true,
            'product_note' => $note,
        ]);
    }

    private function mergeNotes(?string $existing, array $reasons): string
    {
        $existing = trim((string) $existing);
        $parts = $existing !== ''
            ? preg_split('/\s*\|\s*/', $existing, -1, PREG_SPLIT_NO_EMPTY)
            : [];
        foreach ($reasons as $reason) {
            $reason = trim((string) $reason);
            if ($reason !== '' && !in_array($reason, $parts, true)) {
                $parts[] = $reason;
            }
        }

        return implode(' | ', $parts);
    }

    private function cell(Worksheet $sheet, int $columnIndex, int $row)
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex) . $row);
        if ($cell->isFormula()) {
            $cached = $cell->getOldCalculatedValue();
            if ($cached !== null) {
                return $cached;
            }
        }

        return $cell->getCalculatedValue();
    }

    private function withSheet(string $path, string $sheetName, callable $callback, $default = null)
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return $default;
        }

        try {
            return $callback($sheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($sheet, $spreadsheet);
            gc_collect_cycles();
        }
    }

    private function asInt($value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_string($value)) {
            $normalized = preg_replace('/[^0-9\.\-]/', '', $value);
            if ($normalized === '' || $normalized === '-' || $normalized === '.') {
                return $default;
            }
            $value = $normalized;
        }

        return (int) round((float) $value, 0);
    }

    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return $this->normalizeLegacyDate(
                    Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay()
                );
            }

            $text = trim((string) $value);
            foreach (['d-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y'] as $format) {
                $parsed = Carbon::createFromFormat($format, $text);
                if ($parsed !== false) {
                    return $this->normalizeLegacyDate($parsed->startOfDay());
                }
            }

            return $this->normalizeLegacyDate(Carbon::parse($text)->startOfDay());
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeLegacyDate(Carbon $date): Carbon
    {
        if ((int) $date->year === 2029) {
            return $date->copy()->year(2024);
        }

        return $date;
    }
}
