<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BannerRequest;
use App\Models\Banner;
use App\Repositories\BannerRepository;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Display a listing of the banners.
     */
    public function index()
    {
        $rootShop = generaleSetting('rootShop');
        // Get banners
        $banners = Banner::whereNull('shop_id')->orWhere('shop_id', $rootShop->id)->paginate(20);

        return view('admin.banner.index', compact('banners'));
    }

    /**
     * create new banner
     */
    public function create()
    {
        return view('admin.banner.create');
    }

    /**
     * store a new banner
     */
    public function store(BannerRequest $request)
    {
        BannerRepository::storeByRequest($request);

        return to_route('admin.banner.index')->withSuccess(__('Banner created successfully'));
    }

    /**
     * edit a banner
     */
    public function edit(Banner $banner)
    {
        return view('admin.banner.edit', compact('banner'));
    }

    /**
     * update a banner
     */
    public function update(BannerRequest $request, Banner $banner)
    {
        BannerRepository::updateByRequest($request, $banner);

        return to_route('admin.banner.index')->withSuccess(__('Banner updated successfully'));
    }

    /**
     * status toggle a banner
     */
    public function statusToggle(Banner $banner)
    {
        $banner->update([
            'status' => ! $banner->status,
        ]);

        return to_route('admin.banner.index')->withSuccess(__('Banner status updated'));
    }

    /**
     * destroy a banner
     */
    public function destroy(Banner $banner)
    {
        // delete banner
        $media = $banner->media;
        if (Storage::exists($media->src)) {
            Storage::delete($media->src);
        }
        $banner->delete();
        $media->delete();

        return to_route('admin.banner.index')->withSuccess(__('Banner deleted successfully'));
    }
}
