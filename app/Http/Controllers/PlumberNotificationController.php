<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plumber; // Assuming plumbers are stored in the plumbers table
use App\Services\FirebaseService;
use App\Models\NotificationUnique;

class PlumberNotificationController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    // Show the form to send notifications to multiple plumbers
  public function create(Request $request)
{
    $city = $request->input('city');
    $area = $request->input('area');
    $minFixedPoints = $request->input('min_fixed_points');
    $maxFixedPoints = $request->input('max_fixed_points');

    $cities = Plumber::distinct()->pluck('city');
    $areas = Plumber::distinct()->pluck('area');

    $query = Plumber::with('user');

    if ($city) {
        $query->where('city', $city);
    }
    if ($area) {
        $query->where('area', $area);
    }
    if ($minFixedPoints !== null) {
        $query->where('fixed_points', '>=', $minFixedPoints);
    }
    if ($maxFixedPoints !== null) {
        $query->where('fixed_points', '<=', $maxFixedPoints);
    }

    $plumbers = $query->paginate(20); // Display 10 plumbers per page

    return view('admin.plumber_send_notification', compact('plumbers', 'cities', 'areas', 'city', 'area', 'minFixedPoints', 'maxFixedPoints'));
}




  public function sendNotificationMultiple(Request $request)
{
    $request->validate([
        'plumbers' => 'required|array|min:1',
        'title' => 'required',
        'body' => 'required',
    ]);

    // Fetch plumbers with users and their device tokens
    $plumbers = Plumber::whereIn('user_id', $request->plumbers)
        ->with('user')
        ->get()
        ->filter(function ($plumber) {
            return $plumber->user && !empty($plumber->user->device_token);
        });

    if ($plumbers->isEmpty()) {
        return redirect()->back()->with('error', 'No valid plumbers with device tokens found.');
    }

    $successCount = 0;
    $errorCount = 0;

    foreach ($plumbers as $plumber) {
        $deviceToken = $plumber->user->device_token;

        try {
            // Send notification
            $this->firebase->sendNotification($deviceToken, $request->title, $request->body);
            $successCount++;

            // Save notification to database
            NotificationUnique::create([
                'user_id' => $plumber->user_id,
                'title' => $request->title,
                'body' => $request->body,
                'device_token' => $deviceToken,
                'type' => 'multiple',
            ]);
        } catch (\Exception $e) {
            $errorCount++;
        }
    }

    session()->flash('success', "Notification sent successfully to $successCount plumbers.");
    if ($errorCount > 0) {
        session()->flash('error', "$errorCount plumbers had missing device tokens.");
    }

    return redirect()->back();
}




}
