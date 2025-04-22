<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\PlumberGift;

class GiftController extends Controller
{
    // Show the form to create a gift
    public function create()
    {
        return view('gift.create');
    }

    // Handle the form submission and make the API call
    public function store(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:2048', // image should be a file
            'points_required' => 'required|integer|min:0',
        ]);

        // Get the uploaded image file
        $imageFile = $request->file('image');

        // Upload the image to the external API
        $uploadResponse = Http::attach(
            'images', // Correct key for the file expected by the API
            file_get_contents($imageFile->getRealPath()),
            $imageFile->getClientOriginalName()
        )->post('https://app.talentindustrial.com/plumber/upload');

        // Get the response data
        $responseData = $uploadResponse->json();

        // Check if the upload was successful and contains the expected image URL
        if ($uploadResponse->failed() || !isset($responseData['images'][0])) {
            return back()->withErrors(['error' => 'Image upload failed. No URL returned.']);
        }

        // Retrieve the uploaded image URL
        $uploadedImageUrl = $responseData['images'][0]; // The image URL is now in the 'images' array

        // Prepare the data for the gift
        $giftData = [
            'name' => $validated['name'],
            'image' => $uploadedImageUrl, // Use the uploaded image URL
            'points_required' => $validated['points_required'],
        ];

        // Call the API to create the gift
        $response = Http::post('https://app.talentindustrial.com/plumber/gift', $giftData);

        // Check the response
        if ($response->successful()) {
            return redirect()->route('gift.create')->with('success', 'Gift created successfully!');
        }

        return back()->withErrors(['error' => 'Failed to create the gift. Please try again.']);
    }

    // Display all gifts in a table
    public function index()
    {
        $apiUrl = 'https://app.talentindustrial.com/plumber/gift/all?limit=50000000&skip=0'; // API URL for fetching all gifts

        try {
            // Fetch gifts using the GET method
            $response = Http::get($apiUrl);

            if ($response->successful()) {
                $allGifts = collect($response->json()['gifts']['gifts']); // Collect gifts
            } else {
                $allGifts = collect();
                \Log::error('API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            $allGifts = collect();
            \Log::error('Exception: ' . $e->getMessage());
        }
    
        // Filter by name if a search query exists
    $searchQuery = request()->get('name');
    if ($searchQuery) {
        $allGifts = $allGifts->filter(function ($gift) use ($searchQuery) {
            return stripos($gift['name'], $searchQuery) !== false; // Case-insensitive name filter
        });
    }

        // Pagination
        $perPage = 5; // Items per page
        $currentPage = request()->get('page', 1); // Current page
        $paginatedGifts = $allGifts->forPage($currentPage, $perPage);

        $gifts = new LengthAwarePaginator(
            $paginatedGifts,
            $allGifts->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('gift.index', compact('gifts'));
    }


    public function update(Request $request, $id)
    {
        // Validate the input data
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'image' => 'nullable|url',
            'points_required' => 'nullable|integer|min:0',
        ]);

        // Make the API call
        $response = Http::put('https://app.talentindustrial.com/plumber/gift/' . $id, $validated);

        // Check the response
        if ($response->successful()) {
            return redirect()->route('gift.index')->with('success', 'Gift updated successfully!');
        }

        return back()->withErrors(['error' => 'Failed to update the gift. Please try again.']);
    }

public function destroy($id)
{
    $gift = PlumberGift::findOrFail($id);
    $gift->delete();
    return response()->json('Gift is deleted successfully.', 200);
}

}
