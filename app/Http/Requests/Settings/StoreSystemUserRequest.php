<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['full_name', 'user_name', 'phone'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('email')) {
            $payload['email'] = strtolower(trim((string) $this->input('email')));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'user_name' => ['required', 'string', 'max:255', Rule::unique('users', 'user_name')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'min:10', 'max:13', 'regex:/^\d+$/'],
            'password' => ['required', 'string', 'min:6', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/\d/', 'regex:/[^A-Za-z0-9]/'],
            'role_id' => ['required', 'integer', Rule::exists('role_table', 'id')],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
