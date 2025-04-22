<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CertificateController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search'); // Search term
        $dateFrom = $request->get('date_from'); // Date from
        $dateTo = $request->get('date_to'); // Date to

        // Start querying from the 'certificate' table
        $query = Certificate::query();

        // Apply search filters
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('certificate_id', 'LIKE', "%{$search}%")
                  ->orWhere('user_phone', 'LIKE', "%{$search}%")
                  ->orWhereHas('plumber', function ($plumberQuery) use ($search) {
                      $plumberQuery->where('name', 'LIKE', "%{$search}%"); // Searching in users.name
                  });
            });
        }

        // Apply date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        // Paginate results
        $certificates = $query->paginate(20)->appends($request->query());

        return view('admin.certificates', compact('certificates'));
    }
}
