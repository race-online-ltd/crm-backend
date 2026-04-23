<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessMessageJob;

class FacebookWebhookController extends Controller
{
    public function handle(Request $request)
    {
        ProcessMessageJob::dispatch('facebook', $request->all());
        return response()->json(['ok']);
    }
}
