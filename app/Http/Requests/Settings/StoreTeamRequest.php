<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('team_name')) {
            $payload['team_name'] = trim((string) $this->input('team_name'));
        }

        foreach (['supervisor_id', 'kam_id'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = $this->normalizeIds($this->input($field));
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'team_name' => ['required', 'string', 'max:255'],
            'supervisor_id' => ['required', 'array', 'min:1'],
            'supervisor_id.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
            'kam_id' => ['required', 'array', 'min:1'],
            'kam_id.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : [$value];

        return array_values(array_map(
            'intval',
            array_filter($items, static fn ($item) => $item !== null && $item !== '')
        ));
    }
}
