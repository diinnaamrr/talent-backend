<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UniqueProductSubmissionController extends Controller
{
    public function showForm()
    {
        // Fetch categories data from the API
        $response = Http::get('https://app.talentindustrial.com/plumber/category/tree');
        $categories = $response->successful() ? $response->json()['categories'] : [];

        return view('unique_product_form', compact('categories'));
    }

    public function submitProduct(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|url',
            'category_id' => 'required|integer',
            'points' => 'required|integer',
            'product_flag' => 'nullable|boolean',
        ]);

        $response = Http::post('https://app.talentindustrial.com/plumber/category/product', [
            'name' => $validated['name'],
            'image' => $validated['image'],
            'category_id' => $validated['category_id'],
            'points' => $validated['points'],
            'product_flag' => $validated['product_flag'] ?? false,
        ]);

        if ($response->successful()) {
            return back()->with('success', 'Product added successfully!');
        }

        return back()->with('error', 'Failed to add product.');
    }
}
