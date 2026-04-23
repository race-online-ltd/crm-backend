<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessMessageJob;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request)
    {
        ProcessMessageJob::dispatch('whatsapp', $request->all());
        return response()->json(['ok']);
    }
}
