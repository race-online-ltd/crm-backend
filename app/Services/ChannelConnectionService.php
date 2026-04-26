<?php

namespace App\Services;

use App\Models\Social\ChannelConnection;
use App\Models\Social\ChannelConnectionActive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ChannelConnectionService
{
    private const SUPPORTED_CHANNELS = ['facebook', 'whatsapp', 'email'];

    public function listConnections(string $channelKey): Collection
    {
        if (!in_array($channelKey, self::SUPPORTED_CHANNELS, true)) {
            throw new InvalidArgumentException("Unsupported channel: {$channelKey}");
        }

        return ChannelConnection::query()
            ->with([
                'businessEntity',
                'facebookConfig',
                'whatsappConfig',
                'emailConfig',
                'activePointer',
            ])
            ->where('channel_key', $channelKey)
            ->orderBy('business_entity_id')
            ->orderBy('connection_name')
            ->get()
            ->map(fn (ChannelConnection $connection) => $this->transformConnection($connection))
            ->values();
    }

    /**
     * Set a connection as active for its business entity + channel.
     * Atomically replaces whatever was previously active.
     */
    public function setActive(ChannelConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            ChannelConnectionActive::updateOrCreate(
                [
                    'business_entity_id' => $connection->business_entity_id,
                    'channel_key' => $connection->channel_key,
                ],
                [
                    'connection_id' => $connection->id,
                    'set_at' => now(),
                ]
            );

            ChannelConnection::where('business_entity_id', $connection->business_entity_id)
                ->where('channel_key', $connection->channel_key)
                ->update(['is_active' => false]);

            $connection->update([
                'is_active' => true,
                'last_active_at' => now(),
            ]);
        });
    }

    /**
     * Deactivate a connection. Removes the active pointer entirely.
     */
    public function setInactive(ChannelConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            ChannelConnectionActive::where('business_entity_id', $connection->business_entity_id)
                ->where('channel_key', $connection->channel_key)
                ->where('connection_id', $connection->id)
                ->delete();

            $connection->update(['is_active' => false]);
        });
    }

    /**
     * Get the currently active connection for a business entity + channel.
     */
    public function getActive(string $businessEntityId, string $channelKey): ?ChannelConnection
    {
        $pointer = ChannelConnectionActive::where('business_entity_id', $businessEntityId)
            ->where('channel_key', $channelKey)
            ->first();

        return $pointer?->connection;
    }

    /**
     * Save a new connection along with its channel-specific config.
     * $configData must already use the database column names for the selected channel.
     */
    public function saveConnection(
        string $businessEntityId,
        string $channelKey,
        string $connectionName,
        bool $isActive,
        array $configData
    ): ChannelConnection {
        if (!in_array($channelKey, self::SUPPORTED_CHANNELS, true)) {
            throw new InvalidArgumentException("Unsupported channel: {$channelKey}");
        }

        return DB::transaction(function () use (
            $businessEntityId,
            $channelKey,
            $connectionName,
            $isActive,
            $configData
        ) {
            $connection = ChannelConnection::create([
                'business_entity_id' => $businessEntityId,
                'channel_key' => $channelKey,
                'connection_name' => $connectionName,
                'is_active' => false,
            ]);

            match ($channelKey) {
                'facebook' => $connection->facebookConfig()->create($configData),
                'whatsapp' => $connection->whatsappConfig()->create($configData),
                'email' => $connection->emailConfig()->create($configData),
            };

            if ($isActive) {
                $this->setActive($connection);
                $connection->refresh();
            }

            return $connection->load([
                'businessEntity',
                'facebookConfig',
                'whatsappConfig',
                'emailConfig',
                'activePointer',
            ]);
        });
    }

    /**
     * Soft-delete a connection and its active pointer if set.
     */
    public function deleteConnection(ChannelConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            ChannelConnectionActive::where('connection_id', $connection->id)->delete();
            $connection->delete();
        });
    }

    public function transformConnection(ChannelConnection $connection): array
    {
        $config = match ($connection->channel_key) {
            'facebook' => $connection->facebookConfig,
            'whatsapp' => $connection->whatsappConfig,
            'email' => $connection->emailConfig,
            default => null,
        };

        $businessEntity = $connection->businessEntity;
        $isActive = (bool) ($connection->is_active || $connection->activePointer !== null);

        return [
            'id' => $connection->id,
            'channelKey' => $connection->channel_key,
            'businessEntity' => $businessEntity?->name ?? '',
            'connectionName' => $connection->connection_name,
            'isActive' => $isActive,
            'status' => $isActive ? 'active' : 'saved',
            'savedAt' => ($connection->updated_at ?? $connection->created_at)?->toIso8601String(),
            'primaryValue' => $this->resolvePrimaryValue($connection->channel_key, $config),
            'identifiers' => $this->resolveIdentifiers($connection->channel_key, $config),
            'config' => $this->resolveConfigPayload($connection->channel_key, $config),
        ];
    }

    private function resolvePrimaryValue(string $channelKey, mixed $config): string
    {
        return match ($channelKey) {
            'facebook' => (string) ($config?->page_name ?? ''),
            'whatsapp' => (string) ($config?->display_phone_number ?? ''),
            'email' => (string) ($config?->email_address ?? ''),
            default => '',
        };
    }

    private function resolveIdentifiers(string $channelKey, mixed $config): array
    {
        return match ($channelKey) {
            'facebook' => array_values(array_filter([
                $config?->page_id,
                $config?->graph_api_version,
            ])),
            'whatsapp' => array_values(array_filter([
                $config?->business_account_id,
                $config?->phone_number_id,
                $config?->graph_api_version,
            ])),
            'email' => array_values(array_filter([
                $config?->provider_label,
                $config?->imap_host,
                $config?->mailbox,
            ])),
            default => [],
        };
    }

    private function resolveConfigPayload(string $channelKey, mixed $config): array
    {
        return match ($channelKey) {
            'facebook' => [
                'appId' => $config?->app_id ?? '',
                'graphApiVersion' => $config?->graph_api_version ?? '',
                'pageId' => $config?->page_id ?? '',
                'pageName' => $config?->page_name ?? '',
            ],
            'whatsapp' => [
                'appId' => $config?->app_id ?? '',
                'graphApiVersion' => $config?->graph_api_version ?? '',
                'businessAccountId' => $config?->business_account_id ?? '',
                'phoneNumberId' => $config?->phone_number_id ?? '',
                'displayPhoneNumber' => $config?->display_phone_number ?? '',
            ],
            'email' => [
                'providerLabel' => $config?->provider_label ?? '',
                'emailAddress' => $config?->email_address ?? '',
                'imapHost' => $config?->imap_host ?? '',
                'imapPort' => $config?->imap_port ?? '',
                'imapUsername' => $config?->imap_username ?? '',
                'encryption' => $config?->encryption ?? '',
                'mailbox' => $config?->mailbox ?? '',
                'pollingIntervalSeconds' => $config?->polling_interval_seconds ?? '',
            ],
            default => [],
        };
    }
}
