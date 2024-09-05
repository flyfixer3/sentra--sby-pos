<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountingAccountCollectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'subaccounts' => $this->subaccounts,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
