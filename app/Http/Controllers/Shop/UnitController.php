<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

class UnitController extends Controller
{
    /**
     * Display the unit list.
     */
    public function index()
    {
        $shop = generaleSetting('rootShop');

        $units = $shop->units()->paginate(20);

        return view('shop.unit.index', compact('units'));
    }
}
