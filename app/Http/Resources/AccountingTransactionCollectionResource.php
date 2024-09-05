<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

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
            'label' => $this->label,
            'description' => $this->description,
            'date' => $this->date,
            'automated' => $this->automated,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'details' => AccountingTransactionDetailCollectionResource::collection($this->details)
        ];
    }
}
