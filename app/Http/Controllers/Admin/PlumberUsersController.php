<?php
namespace App\Http\Controllers\Admin;

use App\Models\Plumber; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Models\User;

class PlumberUsersController extends Controller
{
public function index(Request $request)
{
    // Get filters from the request
    $statusFilter = $request->input('status');
    $nationalityIdFilter = $request->input('nationality_id');
    $nameFilter = $request->input('name');
    $cityFilter = $request->input('city');
    $phoneFilter = $request->input('phone');
    $monthFilter = $request->input('created_month');

    // Retrieve all unique cities before applying filters
    $allCities = Plumber::pluck('city')->unique()->sort();

    // Retrieve unique months from the created_at column
    $availableMonths = Plumber::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
        ->distinct()
        ->orderBy('month', 'desc')
        ->pluck('month');

    // Retrieve plumbers and apply filters
    $plumbers = Plumber::with('user')
    ->select([
        'id',
        'user_id',
        'city',
        'area',
        'nationality_id',
        'nationality_image1',
        'nationality_image2',
        'is_verified',
        'otp',
        'expiration_date',
        'instant_withdrawal',
        'gift_points',
        'fixed_points',
        'created_at',
        'updated_at',
        'image',
        'withdraw_money'
    ])
        ->when($statusFilter, function ($query) use ($statusFilter) {
            return $query->whereHas('user', function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter);
            });
        })
        ->when($nationalityIdFilter, function ($query) use ($nationalityIdFilter) {
            return $query->where('nationality_id', $nationalityIdFilter);
        })
        ->when($cityFilter, function ($query) use ($cityFilter) {
            return $query->where('city', 'LIKE', '%' . $cityFilter . '%');
        })
        ->when($nameFilter, function ($query) use ($nameFilter) {
            return $query->whereHas('user', function ($query) use ($nameFilter) {
                $query->where('name', 'LIKE', '%' . $nameFilter . '%');
            });
        })
        ->when($phoneFilter, function ($query) use ($phoneFilter) {
            return $query->whereHas('user', function ($query) use ($phoneFilter) {
                $query->where('phone', 'LIKE', '%' . $phoneFilter . '%');
            });
        })
        ->when($monthFilter, function ($query) use ($monthFilter) {
            return $query->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$monthFilter]);
        })
        ->latest()
        ->paginate(20);

    return view('admin.plumberUsers', compact('plumbers', 'allCities', 'availableMonths'));
}




    public function approve(Request $request, $id)
    {
        // Find the plumber by ID and load the related user
        $plumber = Plumber::with('user')->findOrFail($id);

        if (!$plumber->user) {
            return redirect()->route('admin.plumberUsers')->with('error', 'Associated user not found.');
        }

        $userId = $plumber->user->id; 
        $phone = $plumber->user->phone; // Get plumber's phone number

        // Send request to approve plumber
        $response = Http::put("https://app.talentindustrial.com/plumber/plumber/{$userId}/accept");

        if ($response->successful()) {
            // Send SMS Notification
            $message = "تهانينا! تم تفعيل حسابك في برنامج “شكرًا شركاء النجاح”. ابدأ الآن بكسب النقاط والاستفادة من المزايا الحصرية!";
            $this->sendSMS($phone, $message);

            return redirect()->route('admin.plumberUsers')->with('success', 'Plumber approved successfully.');
        }

        return redirect()->route('admin.plumberUsers')->with('error', 'Failed to approve plumber.');
    }

    public function reject(Request $request, $id)
    {
        // Find the plumber by ID and load the related user
        $plumber = Plumber::with('user')->findOrFail($id);

        if (!$plumber->user) {
            return redirect()->route('admin.plumberUsers')->with('error', 'Associated user not found.');
        }

        $userId = $plumber->user->id; 
        $phone = $plumber->user->phone; // Get plumber's phone number

        // Send request to reject plumber
        $response = Http::put("https://app.talentindustrial.com/plumber/plumber/{$userId}/reject");

        if ($response->successful()) {
            // Send SMS Notification
            $message = "نأسف لعدم تفعيل حسابك في برنامج “شكرًا شركاء النجاح”، يرجى استكمال بياناتك لإتمام التفعيل. لمزيد من التفاصيل، تواصل معنا.";
            $this->sendSMS($phone, $message);

            return redirect()->route('admin.plumberUsers')->with('success', 'Plumber rejected successfully.');
        }

        return redirect()->route('admin.plumberUsers')->with('error', 'Failed to reject plumber.');
    }

private function sendSMS($phone, $message)
{
    if (!$phone) {
        \Log::error("SMS Error: No phone number provided.");
        return false; 
    }

    // Ensure phone number starts with "20"
    $formattedPhone = preg_replace('/^0/', '20', $phone);

    // API Credentials (Hardcoded)
    $smsUrl = "https://smsmisr.com/api/sms";
    $smsUsername = "389a30b9f671853179aa4dc08e2c25f2615c48351c59998ca8b475bf667b4823";
    $smsPassword = "86203bf1ae7ee0890041b0e62d4c4533edb5a015504aef0a54e9690ef7a81d5f";
    $smsSender = "aa1b696074eaec8c2063a0d1c394f1d2f35aaffd430399229738916de26b3900";

    // Make API request
    $response = Http::post($smsUrl, [
        'environment' => '1', // 1 for Live, 2 for Test
        'username'    => $smsUsername,
        'password'    => $smsPassword,
        'sender'      => $smsSender,
        'mobile'      => $formattedPhone,
        'language'    => '2', // 1 for English, 2 for Arabic
        'message'     => $message,
    ]);

    // Get API Response
    $responseData = $response->json();

    // Log response for debugging
    \Log::info("SMS API Response: " . json_encode($responseData));

    // Check if SMS was successfully sent
    if (isset($responseData['code']) && $responseData['code'] == "1901") {
        return true;
    }

    \Log::error("SMS Sending Failed: " . json_encode($responseData));
    return false;
}


public function update(Request $request, $id)
{
	 $data = $request->validate([
        'name' => 'nullable|max:50',
        'phone' => 'nullable',
        'instant_withdrawal' => 'nullable|integer',  // Make sure this is an integer
        'gift_points' => 'nullable|integer',          // Make sure this is an integer
        'fixed_points' => 'nullable|integer',         // Make sure this is an integer
    ]);

	$user = User::find($id);
    if (!$user) {
    	return redirect()->route('admin.plumberUsers')->with('error', 'Associated user not found.');
    }
	$user->update($data);
	
	return redirect()->route('admin.plumberUsers')->with('success', 'Plumber updated successfully.');
}

public function destroy($id)
{
    $plumber = Plumber::findOrFail($id);
	$plumber->delete();
	return response()->json('Plumber deleted successfully.', 200);
}

	
}
