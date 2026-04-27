<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChannelConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'business_entity_id' => $this->filled('business_entity_id') ? (int) $this->input('business_entity_id') : null,
            'channel_key' => trim((string) $this->input('channel_key', '')),
            'connection_name' => trim((string) $this->input('connection_name', '')),
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'config' => (array) $this->input('config', []),
        ];

        $this->merge($payload);
    }

    public function rules(): array
    {
        return [
            'business_entity_id' => ['required', 'integer', Rule::exists('business_entities', 'id')],
            'channel_key' => ['required', Rule::in(['facebook', 'whatsapp', 'email'])],
            'connection_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('channel_connections', 'connection_name')->where(fn ($query) => $query
                    ->where('business_entity_id', $this->input('business_entity_id'))
                    ->where('channel_key', $this->input('channel_key'))),
            ],
            'is_active' => ['boolean'],
            'config' => ['required', 'array'],
            'config.app_id' => ['required_if:channel_key,facebook,whatsapp', 'nullable', 'string', 'max:255'],
            'config.app_secret' => ['required_if:channel_key,facebook,whatsapp', 'nullable', 'string'],
            'config.graph_api_version' => ['required_if:channel_key,facebook,whatsapp', 'nullable', 'string', 'max:50'],

            'config.page_id' => ['required_if:channel_key,facebook', 'nullable', 'string', 'max:255'],
            'config.page_name' => ['required_if:channel_key,facebook', 'nullable', 'string', 'max:255'],
            'config.webhook_verify_token' => ['required_if:channel_key,facebook,whatsapp', 'nullable', 'string'],
            'config.page_access_token' => ['required_if:channel_key,facebook', 'nullable', 'string'],

            'config.business_account_id' => ['required_if:channel_key,whatsapp', 'nullable', 'string', 'max:255'],
            'config.phone_number_id' => ['required_if:channel_key,whatsapp', 'nullable', 'string', 'max:255'],
            'config.display_phone_number' => ['required_if:channel_key,whatsapp', 'nullable', 'string', 'max:255'],
            'config.access_token' => ['required_if:channel_key,whatsapp', 'nullable', 'string'],

            'config.provider_label' => ['required_if:channel_key,email', 'nullable', 'string', 'max:255'],
            'config.email_address' => ['required_if:channel_key,email', 'nullable', 'email', 'max:255'],
            'config.imap_host' => ['required_if:channel_key,email', 'nullable', 'string', 'max:255'],
            'config.imap_port' => ['required_if:channel_key,email', 'nullable', 'integer', 'min:1'],
            'config.imap_username' => ['required_if:channel_key,email', 'nullable', 'string', 'max:255'],
            'config.imap_password' => ['required_if:channel_key,email', 'nullable', 'string'],
            'config.encryption' => ['required_if:channel_key,email', 'nullable', Rule::in(['ssl_tls', 'starttls', 'none'])],
            'config.mailbox' => ['required_if:channel_key,email', 'nullable', 'string', 'max:255'],
            'config.polling_interval_seconds' => ['required_if:channel_key,email', 'nullable', 'integer', 'min:1'],
        ];
    }
}
