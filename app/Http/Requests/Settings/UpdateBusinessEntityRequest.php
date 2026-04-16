<?php

namespace App\Http\Requests\Settings;

use App\Models\BusinessEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function rules(): array
    {
        $businessEntity = $this->route('businessEntity');
        $businessEntityId = $businessEntity instanceof BusinessEntity ? $businessEntity->id : $businessEntity;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('business_entities', 'name')->ignore($businessEntityId),
            ],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
