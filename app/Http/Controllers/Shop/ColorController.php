<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

class ColorController extends Controller
{
    /**
     * Display the colors list.
     */
    public function index()
    {
        $shop = generaleSetting('rootShop');

        // Get colors
        $colors = $shop->colors()->paginate(20);

        return view('shop.color.index', compact('colors'));
    }
}
