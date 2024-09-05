<?php

namespace App\Http\Resources;

use App\Models\AccountingSubaccount;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingTransactionDetailCollectionResource extends JsonResource
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
            'subaccount' => new AccountingSubaccountCollectionResource($this->subaccount),
            'amount' => $this->amount,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
