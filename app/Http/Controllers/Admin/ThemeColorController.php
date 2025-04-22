<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneraleSetting;
use App\Repositories\GeneraleSettingRepository;
use App\Repositories\ThemeColorRepository;
use Illuminate\Http\Request;

class ThemeColorController extends Controller
{
    public function index()
    {
        $themeColors = ThemeColorRepository::getAll();

        $generaleSetting = GeneraleSetting::first();

        $primaryColor = ThemeColorRepository::query()->where('is_default', true)->first() ?? null;

        $primary = $primaryColor ? $primaryColor->primary : '#EE456B';
        $secondary = $primaryColor ? $primaryColor->secondary : '#FEE5E8';

        return view('admin.theme-color', compact('themeColors', 'primary', 'secondary'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'primary_color' => 'required',
            'secondary_color' => 'required',
        ]);

        GeneraleSettingRepository::updateOrCreateThemeColor($request);

        ThemeColorRepository::DefaultColorUpdate($request);

        return back()->with('success', __('Theme color updated successfully'));
    }

    public function change(Request $request)
    {
        if (! $request->generated_color_variants) {
            return back()->with('error', __('Please generated color variants'));
        }

        if (app()->environment('local')) {
            return back()->with('demoMode', __('Sorry! You can not change color in demo mode'));
        }

        ThemeColorRepository::updateColorPalette($request);

        return back()->with('success', __('Theme color updated successfully'));
    }
}
