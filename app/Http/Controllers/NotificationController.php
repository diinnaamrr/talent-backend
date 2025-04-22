<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use App\Models\User; 
use App\Models\NotificationUnique;

class NotificationController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

public function sendNotification(Request $request)
{
    $request->validate([
        'token' => 'required',
        'title' => 'required',
        'body' => 'required',
        'user_id' => 'required|exists:users,id',
    ]);

    $user = User::find($request->user_id);

    if ($user) {
        $user->device_token = $request->token;
        $user->save();
    }

    // Save notification to database
    NotificationUnique::create([
        'user_id' => $request->user_id,
        'title' => $request->title,
        'body' => $request->body,
        'device_token' => $request->token,
        'type' => 'single',
    ]);

    // Send Notification
    $firebaseResponse = $this->firebase->sendNotification($request->token, $request->title, $request->body);

    return response()->json([
        'message' => 'Notification sent successfully',
        'device_token' => $request->token,
        'firebase_response' => $firebaseResponse
    ]);
}

public function getUserNotifications($user_id)
{
    // Check if user exists
    $user = User::find($user_id);
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // Fetch notifications for the user
    $notifications = NotificationUnique::where('user_id', $user_id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Notifications retrieved successfully',
        'notifications' => $notifications
    ]);
}

}