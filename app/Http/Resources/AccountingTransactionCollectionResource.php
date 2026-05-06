<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AccountingTransactionCollectionResource extends JsonResource
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
            'entity_id' => $this->entity_id,
            'entity_name' => optional($this->entity)->name,
            'branch_id' => $this->branch_id,
            'branch_name' => optional($this->branch)->name,
            'label' => $this->label,
            'description' => $this->description,
            'date' => Carbon::parse($this->date)->addHours(7),
            'automated' => $this->automated,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'status' => $this->status,
            'posted_at' => $this->posted_at,
            'reversed_at' => $this->reversed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'details' => AccountingTransactionDetailCollectionResource::collection($this->details)
        ];
    }
}
