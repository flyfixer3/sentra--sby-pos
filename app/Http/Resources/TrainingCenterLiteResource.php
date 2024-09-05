<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingCenterLiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->training_center_number,
            'name' => $this->training_center_name,
            'email' => $this->training_center_email,
            'phoneNumber' => $this->training_center_phone_number,
            'address' => $this->training_center_address,
            'owner' => $this->training_center_owner_name,
        ];
    }
}
