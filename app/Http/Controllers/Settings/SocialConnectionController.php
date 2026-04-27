<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreChannelConnectionRequest;
use App\Models\Social\ChannelConnection;
use App\Services\ChannelConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialConnectionController extends Controller
{
    public function __construct(private readonly ChannelConnectionService $channelConnectionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_key' => ['required', Rule::in(['facebook', 'whatsapp', 'email'])],
        ]);

        $rows = $this->channelConnectionService->listConnections($validated['channel_key']);

        return response()->json([
            'message' => 'Social connections fetched successfully.',
            'data' => $rows,
        ]);
    }

    public function store(StoreChannelConnectionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $connection = $this->channelConnectionService->saveConnection(
            (string) $validated['business_entity_id'],
            (string) $validated['channel_key'],
            (string) $validated['connection_name'],
            (bool) ($validated['is_active'] ?? false),
            (array) $validated['config']
        );

        return response()->json([
            'message' => 'Social connection saved successfully.',
            'data' => $this->channelConnectionService->transformConnection($connection),
        ], 201);
    }

    public function activate(ChannelConnection $channelConnection): JsonResponse
    {
        $this->channelConnectionService->setActive($channelConnection);

        return response()->json([
            'message' => 'Social connection activated successfully.',
            'data' => $this->channelConnectionService->transformConnection($channelConnection->fresh([
                'businessEntity',
                'facebookConfig',
                'whatsappConfig',
                'emailConfig',
                'activePointer',
            ])),
        ]);
    }

    public function deactivate(ChannelConnection $channelConnection): JsonResponse
    {
        $this->channelConnectionService->setInactive($channelConnection);

        return response()->json([
            'message' => 'Social connection deactivated successfully.',
            'data' => $this->channelConnectionService->transformConnection($channelConnection->fresh([
                'businessEntity',
                'facebookConfig',
                'whatsappConfig',
                'emailConfig',
                'activePointer',
            ])),
        ]);
    }

    public function destroy(ChannelConnection $channelConnection): JsonResponse
    {
        $this->channelConnectionService->deleteConnection($channelConnection);

        return response()->json([
            'message' => 'Social connection deleted successfully.',
        ]);
    }
}
