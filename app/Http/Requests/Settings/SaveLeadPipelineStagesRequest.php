<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLeadPipelineStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->filled('business_entity_id')) {
            $payload['business_entity_id'] = (int) $this->input('business_entity_id');
        }

        if ($this->has('stages')) {
            $payload['stages'] = collect((array) $this->input('stages'))
                ->map(function ($stage) {
                    $stage = (array) $stage;

                    return [
                        'id' => blank($stage['id'] ?? null) ? null : (int) $stage['id'],
                        'stage_name' => trim((string) ($stage['stage_name'] ?? '')),
                        'color' => strtoupper(trim((string) ($stage['color'] ?? ''))),
                    ];
                })
                ->values()
                ->all();
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'business_entity_id' => ['required', 'integer', Rule::exists('business_entities', 'id')],
            'stages' => ['required', 'array'],
            'stages.*.id' => ['nullable', 'integer', Rule::exists('lead_pipeline_stages', 'id')],
            'stages.*.stage_name' => ['required', 'string', 'max:255'],
            'stages.*.color' => ['required', 'regex:/^#[A-F0-9]{6}$/'],
        ];
    }
}
