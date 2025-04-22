<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlumberCategory;

class FlaggedCategoryController extends Controller
{
    /**
     * Display categories with product_flag set to true.
     */
    public function index(Request $request)
    {
        $query = PlumberCategory::where('product_flag', 1);

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('points') && $request->points != '') {
            $query->where('points', $request->points);
        }

        if ($request->has('category') && $request->category != '') {
            $query->where('parent_id', $request->category);
        }

        $categories = $query->with('subcategories')->paginate(20);
    
// Fetch categories where product_flag is 0 (Only show these in the dropdown)
$dropdownCategories = PlumberCategory::where('product_flag', 0)
        ->whereNull('parent_id') // Fetch only top-level categories
        ->with('activeSubcategories') // Ensure all levels of subcategories are included
        ->get();
        return view('categories.flagged', compact('categories', 'dropdownCategories'));
    }

    /**
     * Store a new product in the database.
     */
   public function store(Request $request)
{
    try {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:plumber_categories,id',
            'points' => 'required|numeric',
            'image' => 'nullable|string',
            'product_flag' => 'sometimes|boolean',
        ]);

        // Extract only the filename if an image is provided
        $imageName = $request->image ? basename($request->image) : null;

        PlumberCategory::create([
            'name' => $request->name,
            'parent_id' => $request->category_id,
            'image' => $imageName,
            'points' => $request->points,
            'product_flag' => $request->product_flag ? 1 : 0,
        ]);

        return response()->json(['message' => 'Product added successfully'], 201);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


    /**
     * Bulk delete flagged categories.
     */
    public function bulkDestroy(Request $request)
    {
        if ($request->delete_all == "1") {
            $query = PlumberCategory::where('product_flag', 1);

            if ($request->search) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            if ($request->points) {
                $query->where('points', $request->points);
            }

            if ($request->category) {
                $query->where('parent_id', $request->category);
            }

            $deletedCount = $query->delete();

            return redirect()->route('flagged.index')->with('success', "Deleted $deletedCount Products");
        }

        $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:plumber_categories,id',
        ]);

        $deletedCount = PlumberCategory::whereIn('id', $request->category_ids)
            ->where('product_flag', 1)
            ->delete();

        return redirect()->route('flagged.index')->with('success', "Deleted $deletedCount selected Products");
    }

public function update(Request $request, $id)
{
    try {
        $category = PlumberCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:plumber_categories,id',
            'points' => 'required|numeric',
            'image' => 'nullable|string',
        ]);

        $imageName = $request->image ? basename($request->image) : $category->image;

        $category->update([
            'name' => $request->name,
            'parent_id' => $request->category_id,
            'points' => $request->points,
            'image' => $imageName,
        ]);

        return response()->json(['message' => 'Category updated successfully', 'data' => $category], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}



}
