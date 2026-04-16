<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('name')) {
            $payload['name'] = trim((string) $this->input('name'));
        }

        foreach (['supervisor_id', 'team_id'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = array_values(array_filter(
                    (array) $this->input($field),
                    static fn ($value) => $value !== null && $value !== ''
                ));
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'supervisor_id' => ['required', 'array', 'min:1'],
            'supervisor_id.*' => ['required', 'string', 'max:255'],
            'team_id' => ['required', 'array', 'min:1'],
            'team_id.*' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
