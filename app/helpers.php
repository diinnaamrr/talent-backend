<?php

use App\Models\DeliveryCharge;
use App\Models\GeneraleSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

if (! function_exists('getDistance')) {
    /**
     * Calculate the distance between two geographical coordinates.
     *
     * @param  array  $firstLatLng  The first [latitude, longitude] coordinates
     * @param  array  $secondLatLng  The second [latitude, longitude] coordinates
     * @return float The distance between the two coordinates in kilometers
     */
    function getDistance(array $firstLatLng, array $secondLatLng): float
    {
        if (empty($firstLatLng) || empty($secondLatLng)) {
            return 0;
        }

        $theta = ($firstLatLng[1] - $secondLatLng[1]);
        $dist = sin(deg2rad($firstLatLng[0])) *
            sin(deg2rad($secondLatLng[0])) +
            cos(deg2rad($firstLatLng[0])) *
            cos(deg2rad($secondLatLng[0])) *
            cos(deg2rad($theta));

        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        return $miles * 1.609344;
    }
}

if (! function_exists('generaleSetting')) {
    /**
     * Get the generale setting Or shop.
     *
     * @param  string|null  $type  = 'setting|shop|rootShop'
     *
     * @type setting|shop|rootShop
     *
     * @default GeneraleSetting
     *
     * @return GeneraleSetting|Shop
     *
     * @throws \Exception
     */
    function generaleSetting($type = null)
    {
        // Cache general setting data for  30 days
        $generaleSetting = Cache::remember('generale_setting', 60 * 24 * 30, function () {
            return GeneraleSetting::first();
        });

        if ($type == 'setting' || $type == null) {
            return $generaleSetting;
        }

        if ($type == 'rootShop') {
            return Cache::remember('admin_shop', 60 * 24 * 7, function () {
                return User::role('root')->whereHas('shop')->first()?->shop;
            });
        }

        if ($type == 'shop') {
            if ($generaleSetting?->shop_type == 'single') {
                $shop = User::role('root')->whereHas('shop')->first()?->shop;
            } else {
                /** @var User */
                $user = auth()->user();
                $shop = $user->shop ?? $user->myShop;
            }

            if (! $shop) {
                $shop = User::role('root')->whereHas('shop')->first()?->shop;
            }

            return $shop;
        }

        return $generaleSetting;
    }
}

if (! function_exists('showCurrency')) {

    /**
     * Show the currency in the given amount.
     *
     * @param  float  $amount
     */
    function showCurrency($amount = null): string
    {
        $generaleSetting = generaleSetting('setting');

        $currency = $generaleSetting?->currency ?? '$';

        $amount = ($amount == 0 || $amount == null) ? 0 : $amount;

        if ($generaleSetting?->currency_position == 'suffix') {
            return $amount.$currency;
        }

        return $currency.$amount;
    }
}

if (! function_exists('getDeliveryCharge')) {

    /**
     * get the delivery charge.
     *
     * @param  int  $orderQuantity
     */
    function getDeliveryCharge($orderQuantity): float
    {
        $deliveryCharge = DeliveryCharge::where('min_qty', '<=', $orderQuantity)
            ->where('max_qty', '>=', $orderQuantity)
            ->first();

        return $deliveryCharge?->charge ?? 0;
    }
}

if (! function_exists('permissionName')) {

    /**
     * get the permission name for the customer readable.
     *
     * @param  string  $permission
     */
    function permissionName($permission): string
    {
        $suctomerReadAbleNames = config('acl.customerReadableNames');

        if (isset($suctomerReadAbleNames[$permission])) {
            return trans($suctomerReadAbleNames[$permission]);
        }

        return trans($permission);
    }
}
