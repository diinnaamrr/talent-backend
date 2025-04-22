<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

class SubCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $shop = generaleSetting('rootShop');

        $subCategories = $shop->subCategories()->latest('id')->paginate(10);

        return view('shop.sub-category.index', compact('subCategories'));
    }
}
