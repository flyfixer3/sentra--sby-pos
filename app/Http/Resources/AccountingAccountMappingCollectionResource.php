<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountingAccountMappingCollectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'entity_id' => $this->entity_id,
            'entity_name' => optional($this->entity)->name,
            'branch_id' => $this->branch_id,
            'branch_name' => optional($this->branch)->name,
            'module' => $this->module,
            'event' => $this->event,
            'label' => $this->label,
            'description' => $this->description,
            'accounting_subaccount_id' => $this->accounting_subaccount_id,
            'subaccount_number' => optional($this->subaccount)->subaccount_number,
            'subaccount_name' => optional($this->subaccount)->subaccount_name,
            'subaccount_display' => $this->subaccount
                ? '(' . $this->subaccount->subaccount_number . ') ' . $this->subaccount->subaccount_name
                : null,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
