<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MeetingRecorderController extends Controller
{
    public function launch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task.id' => ['nullable', 'string', 'max:255'],
            'task.taskType' => ['required', 'string', 'in:virtual_meeting'],
            'task.title' => ['nullable', 'string', 'max:255'],
            'task.lead' => ['nullable', 'string', 'max:255'],
            'task.client' => ['nullable', 'string', 'max:255'],
            'task.scheduledAt' => ['nullable'],
        ]);

        $appUrl = rtrim((string) config('services.meeting_recorder.url', ''), '/');
        $apiUrl = rtrim((string) config('services.meeting_recorder.api_url', $appUrl), '/');
        $loginPath = '/' . ltrim((string) config('services.meeting_recorder.login_path', '/api/v1/auth/login'), '/');
        $redirectPath = '/' . ltrim((string) config('services.meeting_recorder.redirect_path', '/login'), '/');
        $username = (string) config('services.meeting_recorder.username', '');
        $password = (string) config('services.meeting_recorder.password', '');
        $sharedBearer = (string) config('services.meeting_recorder.shared_bearer_token', '');

        if ($appUrl === '') {
            return response()->json([
                'message' => 'Meeting recorder URL is not configured.',
            ], 422);
        }

        if ($username === '' || $password === '') {
            return response()->json([
                'message' => 'Meeting recorder credentials are not configured.',
            ], 422);
        }

        $user = $request->user('api');
        $task = $validated['task'] ?? [];

        $loginRequest = Http::acceptJson();

        if ($sharedBearer !== '') {
            $loginRequest = $loginRequest->withToken($sharedBearer);
        }

        $loginResponse = $loginRequest->post($apiUrl . $loginPath, [
            'username' => $username,
            'password' => $password,
        ]);

        if ($loginResponse->failed()) {
            return response()->json([
                'message' => 'Meeting recorder login failed.',
                'data' => [
                    'status' => $loginResponse->status(),
                    'body' => $loginResponse->json() ?? $loginResponse->body(),
                ],
            ], 502);
        }

        $loginData = $loginResponse->json();
        $accessToken = (string) data_get($loginData, 'access_token', '');
        $refreshToken = (string) data_get($loginData, 'refresh_token', '');

        if ($accessToken === '' || $refreshToken === '') {
            return response()->json([
                'message' => 'Meeting recorder login response did not include the required tokens.',
                'data' => [
                    'body' => $loginData,
                ],
            ], 502);
        }

        $query = http_build_query([
            'source' => 'crm',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'taskId' => $task['id'] ?? '',
            'taskType' => $task['taskType'] ?? '',
            'title' => $task['title'] ?? '',
            'lead' => $task['lead'] ?? '',
            'client' => $task['client'] ?? '',
            'scheduledAt' => $task['scheduledAt'] ?? '',
            'crmUserId' => (string) ($user?->id ?? ''),
            'crmUserName' => (string) ($user?->user_name ?? ''),
            'crmFullName' => (string) ($user?->full_name ?? ''),
            'crmEmail' => (string) ($user?->email ?? ''),
        ]);

        $redirectUrl = $appUrl . $redirectPath;
        $separator = str_contains($redirectUrl, '?') ? '&' : '?';

        return response()->json([
            'message' => 'Meeting recorder launch URL generated successfully.',
            'data' => [
                'launch_url' => $redirectUrl . $separator . $query,
            ],
        ]);
    }
}
