<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AccountingPeriodLockCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => AccountingPeriodLockCollectionResource::collection($this->collection),
            'total' => $this->count(),
        ];
    }
}
