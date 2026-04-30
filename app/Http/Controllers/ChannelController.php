<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChannelController extends Controller
{
    public function index()
    {
        try {
            $channels = DB::table('channels as c')
                ->select('c.id', 'c.channel_name')
                ->orderBy('c.id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $channels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch channels',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}