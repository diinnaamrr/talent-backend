<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\LanguageResource;
use App\Http\Resources\PaymentGatewayResource;
use App\Http\Resources\SocialLinkResource;
use App\Models\GeneraleSetting;
use App\Models\PaymentGateway;
use App\Models\SocialAuth;
use App\Models\SocialLink;
use App\Models\VerifyManage;
use App\Repositories\LanguageRepository;
use App\Repositories\ThemeColorRepository;
use Illuminate\Support\Facades\Cache;

class MasterController extends Controller
{
    /**
     * Get master data for all.
     *
     * @return mixed
     */
    public function index()
    {
        $generaleSetting = Cache::rememberForever('generale_setting', function () {
            return GeneraleSetting::first();
        });

        $paymentGateways = Cache::rememberForever('payment_gateway', function () {
            return PaymentGateway::where('is_active', true)->get();
        });

        $verifyManage = Cache::rememberForever('verify_manage', function () {
            return VerifyManage::first();
        });

        $shopType = $generaleSetting?->shop_type ?? 'multi';

        $socialLinks = SocialLink::whereNotNull('link')->get();

        $themeColor = ThemeColorRepository::query()->where('is_default', true)->first();

        $themeColors = (object) [
            'primary' => $themeColor ? $themeColor['primary'] : '#EE456B',
            'primary50' => $themeColor ? $themeColor['variant_50'] : '#fdecf0',
            'primary100' => $themeColor ? $themeColor['variant_100'] : '#fcdae1',
            'primary200' => $themeColor ? $themeColor['variant_200'] : '#f8b5c4',
            'primary300' => $themeColor ? $themeColor['variant_300'] : '#f58fa6',
            'primary400' => $themeColor ? $themeColor['variant_400'] : '#f16a89',
            'primary500' => $themeColor ? $themeColor['variant_500'] : '#EE456B',
            'primary600' => $themeColor ? $themeColor['variant_600'] : '#d63e60',
            'primary700' => $themeColor ? $themeColor['variant_700'] : '#be3756',
            'primary800' => $themeColor ? $themeColor['variant_800'] : '#a7304b',
            'primary900' => $themeColor ? $themeColor['variant_900'] : '#8f2940',
            'primary950' => $themeColor ? $themeColor['variant_950'] : '#772336',
        ];

        $languages = Cache::remember('languages', 60 * 24, function () {
            return LanguageRepository::getAll();
        });

        $socialAuths = collect([]);
        foreach (SocialAuth::all() as $socialAuth) {
            $socialAuths[$socialAuth->provider] = [
                'name' => $socialAuth->name,
                'client_id' => $socialAuth->client_id,
                'is_active' => (bool) $socialAuth->is_active,
                'redirect_url' => $socialAuth->redirect,
            ];
        }

        return $this->json('Master data', [
            'currency' => [
                'symbol' => $generaleSetting?->currency ?? '$',
                'position' => $generaleSetting?->currency_position ?? 'prefix',
            ],
            'app_name' => $generaleSetting?->name ?? config('app.name'),
            'show_download_app' => (bool) ($generaleSetting?->show_download_app ?? true),
            'google_playstore_link' => $generaleSetting?->google_playstore_url ?? null,
            'app_store_link' => $generaleSetting?->app_store_url ?? null,
            'payment_gateways' => PaymentGatewayResource::collection($paymentGateways),
            'multi_vendor' => (bool) ($shopType == 'multi' ? true : false),
            'mobile' => $generaleSetting?->footer_phone ?? '+88 01365 236 543',
            'email' => $generaleSetting?->footer_email ?? 'support@readyeCommerce.com',
            'address' => $generaleSetting?->address ?? 'Dhaka, Bangladesh',
            'web_show_footer' => (bool) ($generaleSetting?->show_footer ?? true),
            'web_footer_text' => $generaleSetting?->footer_text ?? 'All right reserved by RazinSoft',
            'web_footer_description' => $generaleSetting?->footer_description ?? 'The ultimate all-in-one solution for your eCommerce business worldwide.',
            'web_logo' => $generaleSetting?->logo ?? asset('assets/logo.png'),
            'web_footer_logo' => $generaleSetting?->footerLogo ?? asset('assets/logoWhite.png'),
            'app_name' => $generaleSetting?->name ?? config('app.name'),
            'app_logo' => $generaleSetting?->appLogo ?? asset('assets/favicon.png'),
            'footer_qr' => $generaleSetting?->footerQr ?? null,
            'social_links' => SocialLinkResource::collection($socialLinks),
            'theme_colors' => $themeColors,
            'pusher_app_key' => config('broadcasting.connections.pusher.key'),
            'pusher_app_cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'app_environment' => config('app.env'),
            'register_otp_verify' => (bool) ($verifyManage?->register_otp ?? false),
            'register_otp_type' => $verifyManage?->register_otp_type ?? 'phone',
            'forgot_otp_type' => $verifyManage?->forgot_otp_type ?? 'phone',
            'languages' => LanguageResource::collection($languages),
            'social_auths' => $socialAuths,
        ]);
    }
}
