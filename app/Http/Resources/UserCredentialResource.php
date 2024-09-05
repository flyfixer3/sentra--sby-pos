<?php

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;

class UserCredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'user' => [
                'username' => $this->email,
                'nickname' => $this->name
            ],
            'role' => $this->user_role,
            'token' => $this->token
        ];
    }
}
