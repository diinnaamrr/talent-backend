<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\PlumberReceivedGift;
use App\Exports\GiftExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ReceivedGiftController extends Controller
{
    // Fetch and display gifts without pagination or limit
    public function index(Request $request)
    {
        // API URL without pagination params
//         $apiUrl = "https://app.talentindustrial.com/plumber/receivedGift";

//         try {
//             // Make the GET request to fetch data
//             $response = Http::get($apiUrl);

//             if ($response->successful()) {
//                 $data = $response->json();
//                 $gifts = collect($data['gifts']); // Extract the 'gifts' array
//             } else {
//                 $gifts = collect();
//             }
//         } catch (\Exception $e) {
//             $gifts = collect();
//             \Log::error("API Error: " . $e->getMessage());
//         }
		$giftNameFilter = $request->input('gift_name');
$plumberNameFilter = $request->input('plumber_name');
$statusFilter = $request->input('status');  // Get the selected status filter

$gifts = PlumberReceivedGift::with(['plumber_gift', 'user'])
    ->when($giftNameFilter, function ($query) use ($giftNameFilter) {
        return $query->whereHas('plumber_gift', function ($query) use ($giftNameFilter) {
            $query->where('name', 'LIKE', '%' . $giftNameFilter . '%');
        });
    })
    ->when($plumberNameFilter, function ($query) use ($plumberNameFilter) {
        return $query->whereHas('user', function ($query) use ($plumberNameFilter) {
            $query->where('name', 'LIKE', '%' . $plumberNameFilter . '%');
        });
    })
    ->when($statusFilter, function ($query) use ($statusFilter) {
        return $query->where('status', $statusFilter);  // Filter by status
    })
    ->paginate(10)
    ->withQueryString();

// Return the view with the gifts data
return view('receivedGift.index', ['gifts' => $gifts]);
    }

public function downloadUserGifts($userId)
{
    // Fetch all withdrawals for the given user
    $gifts = PlumberReceivedGift::where('user_id', $userId)->get();

    // Check if withdrawals exist for the user
    if ($gifts->isEmpty()) {
        return redirect()->back()->with('error', 'No gifts found for this user.');
    }
    // Export the data to Excel using the custom export class
    return Excel::download(new GiftExport($gifts), 'user_gifts.xlsx');
}

public function updateStatus(Request $request, $giftId)
{
    $gift = PlumberReceivedGift::find($giftId);

    if (!$gift) {
        return redirect()->back()->with('error', 'Gift not found.');
    }

    // Update the status and the custom 'updatedAt' column
    $gift->status = $request->input('status');
    $gift->updatedAt = now();  // Use the custom 'updatedAt' column name
    $gift->save();

    return redirect()->route('received-gift.index')->with('success', 'Gift status updated successfully.');
}


public function bulkAction(Request $request)
{
    // Validate input
    $request->validate([
        'selected_gifts' => 'required|string', // JSON-encoded string of IDs
        'action' => 'required|string|in:Delivered,Rejected,Cancelled',
    ]);

    // Decode JSON-encoded gift IDs
    $giftIds = json_decode($request->selected_gifts, true);

    if (!is_array($giftIds) || empty($giftIds)) {
        return redirect()->back()->with('error', 'No valid gifts selected.');
    }

    // Update all selected gifts in bulk
    DB::table('plumber_received_gifts')
        ->whereIn('id', $giftIds)
        ->update(['status' => $request->action]);

    return redirect()->route('received-gift.index')->with('success', 'Gifts updated successfully!');
}




public function bulkDownload(Request $request)
{
    $ids = explode(',', $request->query('ids'));

    $gifts = PlumberReceivedGift::whereIn('id', $ids)->get();

    if ($gifts->isEmpty()) {
        return redirect()->back()->with('error', 'No valid gifts selected.');
    }

    // Generate a ZIP file with all gift files
    $zip = new \ZipArchive();
    $zipFileName = storage_path('app/public/bulk_gifts.zip');

    if ($zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
        foreach ($gifts as $gift) {
            if (!empty($gift->plumber_gift->image)) {
                $filePath = public_path("plumber/uploads/{$gift->plumber_gift->image}");
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, basename($filePath));
                }
            }
        }
        $zip->close();
        return response()->download($zipFileName);
    } else {
        return redirect()->back()->with('error', 'Failed to create zip file.');
    }
}

}
