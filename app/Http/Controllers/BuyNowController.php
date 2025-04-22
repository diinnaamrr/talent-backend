<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BuyNowController extends Controller
{
    /**
     * Handle the 'Buy Now' request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function buyNow(Request $request)
    {
        // Example: Validate incoming request data
        $validatedData = $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        // Example: Perform some logic (e.g., process purchase)
        $productId = $validatedData['product_id'];
        $quantity = $validatedData['quantity'];

        // Add your business logic here (e.g., check stock, save order, etc.)
        // Simulated response for demonstration
        return response()->json([
            'message' => 'Purchase processed successfully!',
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }
}
