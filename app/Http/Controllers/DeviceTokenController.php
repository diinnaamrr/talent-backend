<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; // Assuming inspectors are stored in the users table

class DeviceTokenController extends Controller
{
    public function getDeviceToken($inspectorId)
    {
        // Find the user/inspector by ID
        $user = User::find($inspectorId);

        // Check if user exists and has a device token
        if (!$user || !$user->device_token) {
            return response()->json(['error' => 'Device token not found'], 404);
        }

        // Return the device token
        return response()->json(['device_token' => $user->device_token]);
    }
}
