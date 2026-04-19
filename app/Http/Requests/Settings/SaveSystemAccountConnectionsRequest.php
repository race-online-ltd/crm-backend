<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSystemAccountConnectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $connections = $this->input('connections', []);

        if (!is_array($connections)) {
            return;
        }

        $normalizedConnections = array_values(array_filter(array_map(function ($connection) {
            if (!is_array($connection)) {
                return null;
            }

            $externalSystemId = isset($connection['externalSystemId']) && $connection['externalSystemId'] !== ''
                ? (int) $connection['externalSystemId']
                : null;

            $externalUserId = isset($connection['externalUserId'])
                ? trim((string) $connection['externalUserId'])
                : null;

            if ($externalSystemId === null && ($externalUserId === null || $externalUserId === '')) {
                return null;
            }

            return [
                'externalSystemId' => $externalSystemId,
                'externalUserId' => $externalUserId,
            ];
        }, $connections)));

        $this->merge([
            'connections' => $normalizedConnections,
        ]);
    }

    public function rules(): array
    {
        return [
            'connections' => ['sometimes', 'array'],
            'connections.*.externalSystemId' => [
                'required',
                'integer',
                Rule::exists('external_system', 'id'),
            ],
            'connections.*.externalUserId' => ['required', 'string', 'max:255'],
        ];
    }
}
