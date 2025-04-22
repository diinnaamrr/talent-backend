<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdminPlumberNotification;

class AdminPlumberNotificationController extends Controller
{
    public function index()
{
    $notifications = AdminPlumberNotification::latest()->paginate(10); // ✅ 10 per page
    return view('adminplumbernotifications.index', compact('notifications'));
}


    public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'subject' => 'nullable|string|max:255', // ✅ Add subject validation
        'message' => 'required|string',
        'firebase_id' => 'nullable|string',
        'data' => 'nullable|array',
    ]);

    AdminPlumberNotification::create([
        'title' => $request->title,
        'subject' => $request->subject, // ✅ Store subject
        'message' => $request->message,
        'firebase_id' => $request->firebase_id,
        'data' => json_encode($request->data),
        'read' => false
    ]);

    return response()->json(['message' => 'Notification saved successfully']);
}

public function markAsRead($id)
    {
        $notification = AdminPlumberNotification::findOrFail($id);
        $notification->read = true; // ✅ Update the "read" column
        $notification->save();

        return response()->json(['message' => 'Notification marked as read']);
    }

}
