<?php

namespace Modules\Product\Imports;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;
use Modules\Product\Entities\Accessory;

class AccessoriesImport implements OnEachRow, WithHeadingRow, WithValidation
{
    public function rules(): array
    {
        return [
            'accessory_code' => ['required', 'string', 'max:255'],
            'accessory_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function onRow(Row $row)
    {
        $data = $row->toArray();

        $accessoryCode = trim((string) ($data['accessory_code'] ?? ''));
        $accessoryName = trim((string) ($data['accessory_name'] ?? ''));

        if ($accessoryCode === '' || $accessoryName === '') {
            return;
        }

        Accessory::query()->updateOrCreate(
            ['accessory_code' => $accessoryCode],
            ['accessory_name' => $accessoryName]
        );
    }
}
