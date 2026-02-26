<?php

namespace Modules\Product\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class ProductsImport implements OnEachRow, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = (int) $userId;
    }

    public function rules(): array
    {
        return [
            'category_code' => ['required', 'string', 'max:255'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'accessory_code' => ['required', 'string', 'max:255'],
            'accessory_name' => ['nullable', 'string', 'max:255'],

            'product_name' => ['required', 'string', 'max:255'],
            'product_code' => ['required', 'string', 'max:255'],
            'product_barcode_symbology' => ['nullable', 'string', 'max:255'],

            'product_cost' => ['required', 'numeric', 'min:0'],
            'product_price' => ['required', 'numeric', 'min:0'],
            'product_unit' => ['nullable', 'string', 'max:255'],

            'product_order_tax' => ['nullable', 'numeric', 'min:0'],
            'product_tax_type' => ['nullable', 'in:0,1'],
            'product_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function onRow(Row $row)
    {
        $r = $row->toArray();

        $categoryCode = trim((string) ($r['category_code'] ?? ''));
        $categoryName = trim((string) ($r['category_name'] ?? ''));
        $accessoryCode = trim((string) ($r['accessory_code'] ?? ''));
        $accessoryName = trim((string) ($r['accessory_name'] ?? ''));

        $productName = trim((string) ($r['product_name'] ?? ''));
        $productCode = trim((string) ($r['product_code'] ?? ''));
        $barcodeSymbology = trim((string) ($r['product_barcode_symbology'] ?? ''));

        $productCost = (int) ($r['product_cost'] ?? 0);
        $productPrice = (int) ($r['product_price'] ?? 0);
        $productUnit = trim((string) ($r['product_unit'] ?? ''));

        $orderTax = ($r['product_order_tax'] ?? null);
        $orderTax = ($orderTax === '' || $orderTax === null) ? null : (int) $orderTax;

        $taxType = ($r['product_tax_type'] ?? null);
        $taxType = ($taxType === '' || $taxType === null) ? null : (int) $taxType;

        $note = trim((string) ($r['product_note'] ?? ''));

        DB::transaction(function () use (
            $categoryCode,
            $categoryName,
            $accessoryCode,
            $accessoryName,
            $productName,
            $productCode,
            $barcodeSymbology,
            $productCost,
            $productPrice,
            $productUnit,
            $orderTax,
            $taxType,
            $note
        ) {
            // 1) Resolve/Create Category
            $cat = DB::table('categories')->where('category_code', $categoryCode)->first();
            if (!$cat) {
                if ($categoryName === '') {
                    throw new \RuntimeException("Category {$categoryCode} not found and category_name is empty.");
                }
                $catId = DB::table('categories')->insertGetId([
                    'category_code' => $categoryCode,
                    'category_name' => $categoryName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $catId = (int) $cat->id;
            }

            // 2) Resolve/Create Accessory
            $acc = DB::table('accessories')->where('accessory_code', $accessoryCode)->first();
            if (!$acc) {
                if ($accessoryName === '') {
                    $accessoryName = $accessoryCode; // fallback
                }
                DB::table('accessories')->insert([
                    'accessory_code' => $accessoryCode,
                    'accessory_name' => $accessoryName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3) Upsert Product by product_code
            $existing = DB::table('products')->where('product_code', $productCode)->first();

            $payload = [
                'category_id' => $catId,
                'accessory_code' => $accessoryCode,
                'product_name' => $productName,
                'product_code' => $productCode,
                'product_barcode_symbology' => ($barcodeSymbology === '' ? null : $barcodeSymbology),
                'product_cost' => $productCost,
                'product_price' => $productPrice,
                'product_unit' => ($productUnit === '' ? null : $productUnit),
                'product_order_tax' => $orderTax,
                'product_tax_type' => $taxType,
                'product_note' => ($note === '' ? null : $note),
                'updated_at' => now(),
                'updated_by' => $this->userId,
            ];

            if ($existing) {
                DB::table('products')->where('id', (int) $existing->id)->update($payload);
            } else {
                $payload['created_at'] = now();
                $payload['created_by'] = $this->userId;
                DB::table('products')->insert($payload);
            }
        });
    }
}