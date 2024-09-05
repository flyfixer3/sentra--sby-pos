<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountingSubaccountCollectionResource extends JsonResource
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
            'account_name_display' => "($this->subaccount_number) $this->subaccount_name",
            'accounting_account_id' => $this->accounting_account_id,
            'subaccount_number' => $this->subaccount_number,
            'subaccount_name' => $this->subaccount_name,
            'description' => $this->description,
            'total_debit' => $this->total_debit,
            'total_credit' => $this->total_credit,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'accounting_account' => new AccountingAccountCollectionResource($this->account)
        ];
    }
}
