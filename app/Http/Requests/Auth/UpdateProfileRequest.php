<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('full_name')) {
            $payload['full_name'] = trim((string) $this->input('full_name'));
        }

        if ($this->has('email')) {
            $email = trim((string) $this->input('email'));
            $payload['email'] = $email === '' ? null : strtolower($email);
        }

        if ($this->has('phone')) {
            $phone = trim((string) $this->input('phone'));
            $payload['phone'] = $phone === '' ? null : $phone;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(auth('api')->id()),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
