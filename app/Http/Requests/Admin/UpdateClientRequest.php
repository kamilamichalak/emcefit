<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateClientRequest extends StoreClientRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $client = $this->route('client');

        return [
            ...parent::rules(),
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($client->user_id),
            ],
        ];
    }
}
