<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlumberWithdraw;
use App\Models\PlumberGift;
use App\Models\Plumber;
use App\Models\PlumberReceivedGift;
use App\Models\User;
use App\Http\Resources\PlumberWithdrawResource;
use App\Http\Resources\PlumberReceivedGiftResource;
use App\Traits\HttpResponse;

class WithdrawController extends Controller
{
	use HttpResponse;
        // Display withdraw logs
    public function logs($id)
    {

    	$gift = PlumberReceivedGift::where('user_id', $id)->latest('createdAt')->get();
    	$withdraw = PlumberWithdraw::where('requestor_id', $id)->latest('request_date')->get();
    
    $mappedGifts = $gift->map(function ($item) {
        return [
			'amount' => null,
            'payment_identifier' => null,
            'status' => $item->status, // ✅ Status already included for withdrawals
            'transaction_type' => null,
            'request_date' => $item->createdAt,
            'processed_date' => $item->updatedAt,
            'is_gift'=>true,
        	'gift_name'=> $item->plumber_gift->name,
        	'points_required'=> $item->plumber_gift->points_required,
            'image' => $item->plumber_gift->image? 'https://app.talentindustrial.com/plumber/uploads/' . $item->plumber_gift->image: null,
        ];
    });

    $mappedWithdraws = $withdraw->map(function ($item) {
        return [
            'amount' => $item->amount,
            'payment_identifier' => $item->payment_identifier,
            'status'=>$item->status,
            'transaction_type' => $item->transaction_type,
            'request_date' => $item->request_date,
            'processed_date' => $item->processed_date,
            'is_gift'=>false,
        	'gift_name'=>null,
        	'points_required'=>null,
            'image' => null,
        ];
    });

    $mergedData = $mappedGifts->merge($mappedWithdraws)->sortByDesc(function ($item) {
        return $item['createdAt'] ?? $item['request_date'];
    })->values();
    
    $skip = (int) request()->get('skip', 0);
    $mergedData = $mergedData->slice($skip)->values();

    return $this->sendResponse($mergedData, 'Withdraw Request', 200);
    }
}
