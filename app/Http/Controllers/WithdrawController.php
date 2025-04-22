<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\PlumberWithdraw;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PlumberWithdrawExport;

class WithdrawController extends Controller
{
    // Display withdraw requests
    public function index(Request $request)
    {
//         // Fetch data from the API for withdraw requests
//         $response = Http::get('https://app.talentindustrial.com/plumber/withdraw');

//         // Ensure the response is not null before logging
//         if ($response->successful() && $response->json()) {
//             Log::info('API Response for Withdraw Index', ['data' => $response->json()]);
//             $data = $response->json();
//             return view('withdraw.index', compact('data'));
//         }
        // Get the status filter from the request
    $statusFilter = $request->input('status');
    $transactionTypeFilter = $request->input('transaction_type');
    $nameFilter = $request->input('name');
    $phoneFilter = $request->input('phone'); // Add phone filter


    // Retrieve plumbers with their associated users and apply the status filter if present
    $data = PlumberWithdraw::with(['plumber'])
        ->when($statusFilter, function ($query) use ($statusFilter) {
            return $query->where('status', $statusFilter);
        })
   ->when($transactionTypeFilter, function ($query) use ($transactionTypeFilter) {
            return $query->where('transaction_type', $transactionTypeFilter);
        })
   ->when($nameFilter, function ($query) use ($nameFilter) {
            return $query->whereHas('plumber.user', function ($query) use ($nameFilter) {
                $query->where('name','LIKE', '%'. $nameFilter . '%');
            });
        })
    ->when($phoneFilter, function ($query) use ($phoneFilter) {
                return $query->whereHas('plumber.user', function ($query) use ($phoneFilter) {
                    $query->where('phone', 'LIKE', '%' . $phoneFilter . '%');
                });
            })
    	->latest('request_date')
        ->paginate(5)->withQueryString();
    
    	// $data = PlumberWithdraw::with(['plumber'])->get();
    	// return $data;
    	if($data)
        {
        	Log::info('API Response for Withdraw Index', ['data' => $data]);
        	return view('withdraw.index', compact('data'));
        }

        // Log error if API fails or returns no data
        Log::error('Failed to fetch withdraw data', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        // Return an error view if the API fails or returns no data
        return view('withdraw.index')->with('error', 'Failed to fetch data.');
    }

    // Download Excel
    public function download()
    {
        // Fetch the download file directly from the API
        $response = Http::get('https://app.talentindustrial.com/plumber/withdraw/download');

        // Check if the response is successful
        if ($response->successful()) {
            // Get the file content from the response
            $fileContent = $response->body();

            // Get the correct filename or set a default name
            $fileName = 'withdraw_requests.csv';

            // Save the file locally (optional)
            Storage::disk('public')->put($fileName, $fileContent);

            // Return the file as a download
            return response()->download(storage_path("app/public/{$fileName}"));
        } else {
            // Log and return error if the API request fails
            Log::error('Failed to fetch data for download', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return redirect()->route('withdraw.index')->with('error', 'Failed to fetch data for download.');
        }
    }
	public function upload(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx|max:2048', // Allow CSV or Excel files up to 2 MB
        ]);

        // Get the uploaded file
        $file = $request->file('file');

        // Send the file to the API as form data
        $response = Http::attach(
            'file', // The form field name expected by the API
            file_get_contents($file->getRealPath()), // File contents
            $file->getClientOriginalName() // Original file name
        )->post('https://app.talentindustrial.com/plumber/withdraw/upload'); // Correct API endpoint

        // Handle API response
        if ($response->successful()) {
            return redirect()->route('withdraw.index')->with('success', 'File uploaded successfully.');
        } else {
            // Log the error for debugging purposes
            Log::error('File upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return redirect()->route('withdraw.index')->with('error', 'Failed to upload file.');
        }
    }

    // Display withdraw logs
   public function logs($userId)
{
    // Get ALL withdraws for that user (not just the first)
    $withdraws = PlumberWithdraw::with(['plumber.user'])
                                ->where('requestor_id', $userId)
                                ->get();

    // If none found, return an empty array (200 OK so the .catch won't trigger)
    if ($withdraws->isEmpty()) {
        return response()->json([], 200);
    }

    return response()->json($withdraws, 200);
}


public function downloadUserWithdrawals($userId)
{
    // Fetch all withdrawals for the given user
    $withdrawals = PlumberWithdraw::where('requestor_id', $userId)->get();

    // Check if withdrawals exist for the user
    if ($withdrawals->isEmpty()) {
        return redirect()->back()->with('error', 'No withdrawal requests found for this user.');
    }
    // Export the data to Excel using the custom export class
    return Excel::download(new PlumberWithdrawExport($withdrawals), 'history_withdrawals.xlsx');
}

public function destroy($id)
{
    $withdraw = PlumberWithdraw::findOrFail($id);
	$withdraw->delete();
	return response()->json('Withdraw request deleted successfully.', 200);
}

    public function updateStatus(Request $request, PlumberWithdraw $withdraw)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);
        $withdraw->status = $request->status;
    
        // Set the instant_withdrawal to 0 on the related Plumber model
    	$plumber = $withdraw->plumber;
    	$plumber->instant_withdrawal = 0;
    	$plumber->withdraw_money = 0;

    	// Save the plumber model to persist the change
    	$plumber->save();
        $withdraw->save();
        return redirect()->route('withdraw.index')->with('success', 'Status changed successfully.');
    }

}