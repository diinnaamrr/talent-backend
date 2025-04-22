<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class DeletionRequestController extends Controller
{
    // Store deletion request
    public function store(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Save request in database
        DeletionRequest::create([
            'user_id' => $request->input('user_id')
        ]);

        return response()->json(['message' => 'Deletion request saved successfully'], 201);
    }

    // Get all deletion requests
    public function index()
    {
        $requests = DeletionRequest::with('user')->get();

        return response()->json($requests);
    }
public function showRequests()
{
    $requests = DeletionRequest::with('user')->get();
    return view('admin.deletion_requests', compact('requests'));
}

}
