<?php

namespace App\Http\Controllers;

use App\Models\DeletionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // Method to request account deletion
    public function requestDeleteAccount(Request $request)
    {
        $user = Auth::user();  // Get the currently authenticated user

        // Check if the user has already requested deletion
        if ($user->deletionRequests()->exists()) {
            return response()->json(['error' => 'You have already requested account deletion.'], 400);
        }

        // Create a new deletion request
        $deletionRequest = new DeletionRequest([
            'user_id' => $user->id,
            'approved' => false  // Initially, it's not approved
        ]);
        $deletionRequest->save();

        return response()->json([
            'message' => 'Your account deletion request has been submitted. It will be reviewed shortly.'
        ]);
    }
public function getDeletionRequests()
{
    $requests = DeletionRequest::with('user')->where('approved', false)->get();

    return response()->json([
        'requests' => $requests
    ]);
}

}
