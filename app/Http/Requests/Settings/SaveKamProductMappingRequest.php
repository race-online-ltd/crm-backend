<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKamProductMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->filled('user_id')) {
            $payload['user_id'] = (int) $this->input('user_id');
        }

        if ($this->filled('business_entity_id')) {
            $payload['business_entity_id'] = (int) $this->input('business_entity_id');
        }

        if ($this->has('product_ids')) {
            $payload['product_ids'] = array_values(array_map(
                'intval',
                array_filter(
                    (array) $this->input('product_ids'),
                    static fn ($value) => $value !== null && $value !== ''
                )
            ));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'business_entity_id' => ['required', 'integer', Rule::exists('business_entities', 'id')],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('product', 'id')],
        ];
    }
}
