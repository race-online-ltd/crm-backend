<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSystemUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('search')) {
            $payload['search'] = trim((string) $this->input('search'));
        }

        if ($this->filled('role_id')) {
            $payload['role_id'] = (int) $this->input('role_id');
        }

        if ($this->filled('page')) {
            $payload['page'] = (int) $this->input('page');
        }

        if ($this->filled('per_page')) {
            $payload['per_page'] = (int) $this->input('per_page');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'integer', Rule::exists('role_table', 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
