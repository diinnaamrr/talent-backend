<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\Withdraw;
use App\Repositories\FlashSaleRepository;

class DashboardController extends Controller
{


public function chooseDashboard()
{
    return view('admin.choose-dashboard');
}

public function setDashboardType($type)
{
    session(['dashboard_type' => $type]);
    return redirect()->route('admin.dashboard.index');
}

    /**
     * Show the application dashboard.
     */
    public function index()
    {
        $generleSetting = generaleSetting('setting');

        $totalCustomer = Customer::count();

        $shop = null;

        if ($generleSetting?->shop_type != 'single') {
            $totalShop = Shop::count();
            $totalOrder = Order::count();
            $totalProduct = Product::count();
            $totalCategories = Category::count();
        } else {
            $shop = generaleSetting('shop');
            $totalShop = 0;
            $totalOrder = Order::where('shop_id', $shop?->id)->count();
            $totalProduct = Product::where('shop_id', $shop?->id)->count();
            $totalCategories = Category::whereHas('shops', function ($query) use ($shop) {
                $query->where('id', $shop?->id);
            })->count();
        }

        $orderStatuses = OrderStatus::cases();

        $topCustomers = Customer::withCount('orders')->orderBy('orders_count', 'desc')->limit(8)->get();

        $productObject = Product::when($shop, function ($query) use ($shop) {
            return $query->where('shop_id', $shop?->id);
        });

        $topSellingProducts = (clone $productObject)->withCount('orders')->orderBy('orders_count', 'desc')->limit(8)->get();

        $topReviewProducts = (clone $productObject)->withAvg('reviews as average_rating', 'rating')->orderBy('average_rating', 'desc')->limit(8)->get();

        $topShops = Shop::withCount('orders')->orderBy('orders_count', 'desc')->withAvg('reviews as average_rating', 'rating')->orderBy('average_rating', 'desc')->limit(8)->get();

        $latestOrders = Order::when($shop, function ($query) use ($shop) {
            return $query->where('shop_id', $shop?->id);
        })->latest('id')->limit(6)->get();

        $topFavorites = (clone $productObject)->whereHas('favorites')->withCount('favorites')->orderBy('favorites_count', 'desc')->limit(8)->get();

        $pendingWithdraw = Withdraw::where('status', 'pending')->sum('amount');
        $alreadyWithdraw = Withdraw::where('status', 'approved')->sum('amount');
        $deniedWithddraw = Withdraw::where('status', 'denied')->sum('amount');

        $totalCommission = Transaction::where('is_commission', true)->sum('amount');

        $flashSale = FlashSaleRepository::getIncoming();

        return view('admin.dashboard', compact('totalShop', 'totalOrder', 'totalCustomer', 'totalProduct', 'orderStatuses', 'topCustomers', 'topSellingProducts', 'topReviewProducts', 'topShops', 'latestOrders', 'topFavorites', 'pendingWithdraw', 'alreadyWithdraw', 'deniedWithddraw', 'totalCommission', 'totalCategories', 'flashSale'));
    }
}
