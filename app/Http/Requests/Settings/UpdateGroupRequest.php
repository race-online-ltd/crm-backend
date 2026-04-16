<?php

namespace App\Http\Requests\Settings;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('group_name')) {
            $payload['group_name'] = trim((string) $this->input('group_name'));
        }

        foreach (['supervisor_id', 'team_id'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = array_values(array_map(
                    'intval',
                    array_filter(
                        (array) $this->input($field),
                        static fn ($value) => $value !== null && $value !== ''
                    )
                ));
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        $group = $this->route('group');
        $groupId = $group instanceof Group ? $group->id : $group;

        return [
            'group_name' => ['required', 'string', 'max:255'],
            'supervisor_id' => ['required', 'array', 'min:1'],
            'supervisor_id.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
            'team_id' => ['required', 'array', 'min:1'],
            'team_id.*' => ['required', 'integer', 'distinct', Rule::exists('teams', 'id')],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
