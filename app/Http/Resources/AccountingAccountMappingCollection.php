<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AccountingAccountMappingCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => AccountingAccountMappingCollectionResource::collection($this->collection),
            'total' => $this->count(),
        ];
    }
}
