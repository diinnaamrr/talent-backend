<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a category listing.
     */
    public function index(Request $request)
    {
        $search = $request->search ?? null;

        $shop = generaleSetting('rootShop');

        // Get categories with search and pagination
        $categories = $shop->categories()->when($search, function ($query) use ($search) {
            return $query->where('name', 'like', '%'.$search.'%');
        })->paginate(20);

        return view('shop.category.index', compact('categories'));
    }
}
