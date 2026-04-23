<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveApprovalPipelineStepsRequest extends FormRequest
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

        if ($this->has('steps')) {
            $payload['steps'] = collect((array) $this->input('steps'))
                ->map(function ($step) {
                    $step = (array) $step;

                    return [
                        'id' => blank($step['id'] ?? null) ? null : (int) $step['id'],
                        'user_id' => blank($step['user_id'] ?? null) ? null : (int) $step['user_id'],
                        'level' => blank($step['level'] ?? null) ? null : (int) $step['level'],
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
            'steps' => ['required', 'array'],
            'steps.*.id' => ['nullable', 'integer', Rule::exists('approval_pipeline_steps', 'id')],
            'steps.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'steps.*.level' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
