<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SegmentController extends Controller
{
    // Fetch all segments from the API and display them
   public function index()
{
    $segmentsApiUrl = 'https://app.talentindustrial.com/plumber/segment/all';
    $configApiUrl = 'https://app.talentindustrial.com/plumber/config/all';

    // Fetch segments from the API
    $segmentsResponse = Http::get($segmentsApiUrl);

    // Fetch configuration data to retrieve "withdraw_points", "fixed_points" and "total_value"
    $configResponse = Http::get($configApiUrl);

   

    if ($segmentsResponse->successful()) {
        $segments = $segmentsResponse->json()['segments'];
    }

    if ($configResponse->successful()) {
        // Extract "withdraw_points" value
        $withdrawPoints = collect($configResponse->json())
            ->firstWhere('key', 'withdraw_points')['value'] ?? 'N/A';

        
    }

    // Pass segments, withdrawPoints, fixedPoints, and totalValue to the view
    return view('segments.index', compact('segments', 'withdrawPoints'));
}



    // Add a new segment
    public function store(Request $request)
    {
        $apiUrl = 'https://app.talentindustrial.com/plumber/segment';

        // Send POST request with form data
        $response = Http::post($apiUrl, [
            'description' => $request->input('description'),
            'minPoints' => $request->input('minPoints'),
            'maxPoints' => $request->input('maxPoints'),
            'pointsValue' => $request->input('pointsValue'),
        ]);

        if ($response->successful()) {
            return redirect()->route('segments.index')->with('success', 'Segment added successfully.');
        }

        return back()->withErrors(['error' => 'Failed to add segment. Please try again.']);
    }
public function update(Request $request, $id)
{
    // Validate input data
    $request->validate([
        'description' => 'required|string|max:255',
        'minPoints' => 'required|integer|min:0',
        'maxPoints' => 'required|integer|gte:minPoints',
        'pointsValue' => 'required|numeric|min:0',
    ]);

    // API URL to update a segment
    $apiUrl = "https://app.talentindustrial.com/plumber/segment/$id";

    // Make a PUT request with the updated data
    $response = Http::put($apiUrl, [
        'description' => $request->input('description'),
        'minPoints' => $request->input('minPoints'),
        'maxPoints' => $request->input('maxPoints'),
        'pointsValue' => $request->input('pointsValue'),
    ]);

    if ($response->successful()) {
        return redirect()->route('segments.index')->with('success', 'Segment updated successfully.');
    }

    return back()->withErrors(['error' => 'Failed to update the segment. Please try again.']);
}

public function updateWithdrawPoints(Request $request)
{
    // Validate the incoming request
    $request->validate([
        'withdraw_points' => 'required|numeric',
    ]);

    $apiUrl = 'https://app.talentindustrial.com/plumber/config/';
    
    // Prepare the data to send in the POST request
    $data = [
        'key' => 'withdraw_points',
        'value' => $request->input('withdraw_points'),
    ];

    // Send the POST request
    $response = Http::post($apiUrl, $data);

    // Log the response for debugging (API response)
    \Log::info('API Response:', $response->json());

    // Check if the request was successful
    if ($response->successful()) {
        // Redirect with success message
        return redirect()->route('segments.index')->with('success', 'Withdraw Points updated successfully!');
    }

    // Log the error for debugging (in case of failure)
    \Log::error('API Error:', $response->json());

    // Return with error message if API request fails
    return back()->withErrors(['error' => 'Failed to update Withdraw Points. Please try again.']);
}


}
