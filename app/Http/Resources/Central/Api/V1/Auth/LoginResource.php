<?php

namespace App\Http\Resources\Central\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        $abilities = $user->getAllPermissions()->pluck('name')->toArray();

        $token = $user->createToken(
            name: 'central-token',
            abilities: $abilities ?: ['*'],
        )->plainTextToken;

        return [
            'token' => $token,
            'type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }
}
