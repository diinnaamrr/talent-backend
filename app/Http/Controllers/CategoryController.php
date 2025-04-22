<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Category;
use App\Models\PlumberCategory;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryController extends Controller
{
    // Existing method to display categories on the main page
public function index(Request $request)
{
    // Clean request parameters: treat empty or null as 'all'
    $level = $request->filled('level') && $request->level !== '' ? $request->level : 'all';
    $parentId = $request->filled('parent_id') && $request->parent_id !== '' ? $request->parent_id : 'all';

    // Fetch categories with eager loading (base query)
    $query = PlumberCategory::where('product_flag', 0)
        ->with(['subcategories', 'parent']);

    // Apply search filter if provided
    if ($request->has('search') && $request->search !== '') {
        $search = strtolower($request->search);
        $query->where('name', 'LIKE', '%' . $search . '%');
    }

    // Apply parent filter if not 'all'
    if ($parentId !== 'all') {
        $query->where('parent_id', (int)$parentId);
    }

    // Fetch the categories from the database
    $categories = $query->get();

    // Calculate category levels
    $categoriesWithLevels = $this->calculateCategoryLevels($categories);

    // Apply level filter if not 'all'
    if ($level !== 'all') {
        $desiredLevel = (int)$level;

        // Filter the categories by the level
        $categoriesWithLevels = $categoriesWithLevels->filter(function ($category) use ($desiredLevel) {
            return $category->level === $desiredLevel;
        });
    }

    // Paginate the categories
    $categories = $this->paginate($categoriesWithLevels, 20);

    // Fetch dropdown categories for filters (product_flag = 0)
$dropdownCategories = PlumberCategory::where('product_flag', 0)->get();

    // Pass the data to the view
    return view('categories.index', compact('categories', 'dropdownCategories'));
}



private function calculateCategoryLevels($categories)
{
    // Map through categories and assign levels based on parent_id hierarchy
    return collect($categories)->map(function ($category) {
        $category->level = $this->getCategoryLevel($category);
        return $category;
    });
}

private function getCategoryLevel($category)
{
    // Calculate the level based on parent_id
    $level = 0;
    $parent = $category->parent_id; // Assuming parent_id column is present

    // Loop to trace the parent category hierarchy
    while ($parent) {
        // Check if the parent exists, and get its parent_id
        $parentCategory = \DB::table('plumber_categories')->where('id', $parent)->first();
        
        if (!$parentCategory) {
            // If no parent is found, break the loop to prevent null access
            break;
        }

        $level++;
        $parent = $parentCategory->parent_id;
    }

    return $level + 1; // Return the level, incremented by 1
}


private function paginate($items, $perPage)
{
    // Paginate the categories collection manually
    $currentPage = LengthAwarePaginator::resolveCurrentPage();
    $currentItems = $items->slice(($currentPage - 1) * $perPage, $perPage)->values();

    // Create a LengthAwarePaginator instance to handle pagination
    return new LengthAwarePaginator(
        $currentItems,
        $items->count(),
        $perPage,
        $currentPage,
        ['path' => LengthAwarePaginator::resolveCurrentPath()]
    );
}





    // New method to display the add/edit categories page
    public function addEditCategory()
    {
        // Fetch categories data from the API
        $response = Http::get('https://app.talentindustrial.com/plumber/category/tree');
        $categories = $response->json()['categories'];

        // Return the add/edit categories view
        return view('categories.add-edit-category', compact('categories'));
    }

    // Additional method for handling the edit functionality
    public function updateCategory(Request $request, $id)
    {
        // Send PUT request to update the category or subcategory
        $response = Http::put("https://app.talentindustrial.com/plumber/category/{$id}", [
            'name' => $request->name,
            // 'points' => $request->points,
            'itemsFlag' => $request->itemsFlag,
        
        ]);

        if ($response->successful()) {
            return response()->json(['message' => 'Category updated successfully!']);
        } else {
            return response()->json(['message' => 'Error updating category.'], 500);
        }
    }

public function destroy($id)
{
    $category = PlumberCategory::findOrFail($id);
	$category->delete();
	return response()->json('Category deleted successfully.', 200);
}
public function bulkDestroy(Request $request)
{
    if ($request->delete_all == "1") {
        // Delete only categories where product_flag = 0 and match applied filters
        $query = PlumberCategory::where('product_flag', 0);

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->parent_id) {
            $query->where('parent_id', (int)$request->parent_id);
        }

        if ($request->level) {
            $level = (int)$request->level;
            $categories = $query->get();

            // Calculate levels and filter
            $categoriesWithLevels = $this->calculateCategoryLevels($categories);
            $filteredCategories = $categoriesWithLevels->filter(fn($cat) => $cat->level === $level);

            // Delete only the filtered ones
            PlumberCategory::whereIn('id', $filteredCategories->pluck('id'))->delete();
        } else {
            // If no level filter, delete all matching records
            $query->delete();
        }

        return redirect()->route('categories.index')->with('success', 'All matching categories have been deleted.');
    }

    // If "Select All in Database" is not checked, delete only selected ones
    $request->validate([
        'category_ids' => 'required|array',
        'category_ids.*' => 'exists:plumber_categories,id',
    ]);

    PlumberCategory::whereIn('id', $request->category_ids)
        ->where('product_flag', 0)
        ->delete();

    return redirect()->route('categories.index')->with('success', 'Selected categories have been deleted.');
}

	
}
