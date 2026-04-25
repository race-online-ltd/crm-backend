<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetApprovalPipelineStepsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('business_entity_id')) {
            $this->merge([
                'business_entity_id' => (int) $this->input('business_entity_id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'business_entity_id' => ['required', 'integer', Rule::exists('business_entities', 'id')],
        ];
    }
}
