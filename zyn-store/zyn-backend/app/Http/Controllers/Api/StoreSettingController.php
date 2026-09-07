<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StoreSettingsService;

class StoreSettingController extends Controller
{
    public function index(StoreSettingsService $settings)
    {
        return response()->json([
            'data' => $settings->publicPayload(),
        ]);
    }
}
