<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackofficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('backoffice_name')) {
            $payload['backoffice_name'] = trim((string) $this->input('backoffice_name'));
        }

        if ($this->filled('business_entity_id')) {
            $payload['business_entity_id'] = (int) $this->input('business_entity_id');
        }

        if ($this->has('user_ids') && is_array($this->input('user_ids'))) {
            $payload['user_ids'] = array_values(array_map('intval', $this->input('user_ids')));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'backoffice_name' => ['required', 'string', 'max:255'],
            'business_entity_id' => ['required', 'integer', Rule::exists('business_entities', 'id')],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }
}
