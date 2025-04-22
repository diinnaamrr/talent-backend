<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Size;

class SizeController extends Controller
{
    /**
     * Display the size list.
     */
    public function index()
    {
        $shop = generaleSetting('rootShop');

        $sizes = $shop->sizes()->paginate(20);

        return view('shop.size.index', compact('sizes'));
    }
}
