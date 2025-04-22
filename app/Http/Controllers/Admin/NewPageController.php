<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\AdminPlumberNotification;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class NewPageController extends Controller
{
    public function index()
    {
        $envoyUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'envoy');
        })->get();

        $apiUrl = 'https://app.talentindustrial.com/plumber/request?limit=50000000&skip=0';

        try {
            $response = Http::get($apiUrl);

            if ($response->successful()) {
                $requests = collect($response->json()['requests']);
            } else {
                $requests = collect();
                Log::error('API Error: ' . $response->body());
            }
        } catch (\Exception $e) {
            $requests = collect();
            Log::error('Exception: ' . $e->getMessage());
        }

        // Filter only requests with status "SEND"
        $sendRequests = $requests->where('status', 'SEND');

        // Store only new "SEND" requests as notifications
        $this->storeNewRequestsAsNotifications($sendRequests);

        // Create a map of envoy user IDs to their names
        $envoyUserMap = $envoyUsers->pluck('name', 'id');

        // Map `assigned_envoy` for each request
        $requests = $requests->map(function ($request) use ($envoyUserMap) {
            $request['assigned_envoy'] = $envoyUserMap->get($request['inspector_id'], 'N/A');
            return $request;
        });

        // Filtering logic (unchanged)
        $city = request()->get('city');
        $area = request()->get('area');
        $status = request()->get('status');
        $userPhone = request()->get('phone');
        $assignedTo = request()->get('assigned_to');
        $dateFrom = request()->get('date_from');
        $dateTo = request()->get('date_to');

        if ($city) {
            $requests = $requests->where('city', $city);
        }

        if ($area) {
            $requests = $requests->where('area', $area);
        }

        if ($status) {
            $requests = $requests->where('status', $status);
        }

        if ($userPhone) {
            // Filter envoy users by phone
            $filteredUsers = $envoyUsers->filter(function ($user) use ($userPhone) {
                return stripos($user->phone, $userPhone) !== false;
            });

            // Collect IDs of filtered envoy users
            $filteredUserIds = $filteredUsers->pluck('id')->toArray();

            // Filter requests by requestor's phone number
            $requests = $requests->filter(function ($request) use ($userPhone) {
                return isset($request['requestor']['phone']) && stripos($request['requestor']['phone'], $userPhone) !== false;
            });
        }

        if ($assignedTo) {
            $requests = $requests->filter(function ($request) use ($assignedTo) {
                return isset($request['inspector_id']) && $request['inspector_id'] == $assignedTo;
            });
        }

        if ($dateFrom) {
            $requests = $requests->filter(function ($request) use ($dateFrom) {
                return isset($request['createdAt']) && $request['createdAt'] >= $dateFrom;
            });
        }

        if ($dateTo) {
            $requests = $requests->filter(function ($request) use ($dateTo) {
                return isset($request['createdAt']) && $request['createdAt'] <= $dateTo;
            });
        }

        // Get distinct cities, areas, and statuses for the dropdown
        $cities = $requests->pluck('city')->unique();
        $areas = $requests->pluck('area')->unique();
        $statuses = ['APPROVED', 'REJECTED', 'ASSIGNED']; // Define possible statuses

        // Pagination
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $paginatedRequests = $requests->forPage($currentPage, $perPage);

        $requests = new LengthAwarePaginator(
            $paginatedRequests,
            $requests->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('admin.newPage', compact('requests', 'envoyUsers', 'cities', 'areas', 'statuses'));
    }

    // ✅ Function to Store Only New "SEND" Requests as Notifications
    private function storeNewRequestsAsNotifications($sendRequests)
    {
        foreach ($sendRequests as $request) {
            // Avoid duplicate notifications by checking if request_id exists
            $exists = AdminPlumberNotification::where('data->request_id', $request['id'])->exists();

            if (!$exists) {
                AdminPlumberNotification::create([
                    'title' => 'New Service Request',
                    'subject' => 'New Request Received',
                    'message' => "A new service request with ID: {$request['id']} is now available in {$request['city']}.",
                    'firebase_id' => null, // Firebase can be added later
                    'data' => json_encode([
                        'request_id' => $request['id'],
                        'city' => $request['city'],
                        'status' => $request['status'],
                    ]),
                    'read' => false,
                ]);
            }
        }
    }
}
