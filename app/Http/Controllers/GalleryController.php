<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Image; // Import the Image model
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ImagesExport;

class GalleryController extends Controller
{
   public function index()
{
    $images = Image::paginate(18); 
    return view('dashboard.gallery', compact('images'));
}

 public function upload(Request $request)
    {
        $request->validate([
            'images.*' => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max per image
        ]);

        $uploadedImages = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                // Send each image to the external API
                $response = Http::asMultipart()
                    ->attach('images', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
                    ->post('https://app.talentindustrial.com/plumber/upload');

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['images']) && count($data['images']) > 0) {
                        $imageUrl = $data['images'][0];
                        $imageName = $image->getClientOriginalName();

                        // Save image to the database
                        Image::create([
                            'filename' => $imageName,
                            'url' => $imageUrl,
                        ]);

                        $uploadedImages[] = $imageUrl;
                    }
                }
            }
        }

        if (!empty($uploadedImages)) {
            return redirect()->back()->with('success', 'Images uploaded successfully!');
        }

        return redirect()->back()->with('error', 'Image upload failed. Please try again.');
    }
public function delete($id)
{
    $image = Image::findOrFail($id);
    
    // Attempt to delete from external storage if needed (if API supports it)
    // Http::delete('https://app.talentindustrial.com/plumber/delete', ['url' => $image->url]);

    // Delete the image from the database
    $image->delete();

    return redirect()->back()->with('success', 'Image deleted successfully!');
}

public function deleteAll()
{
    // Retrieve all images
    $images = Image::all();

    // Optionally: If you need to delete from external storage
    // foreach ($images as $image) {
    //     Http::delete('https://app.talentindustrial.com/plumber/delete', ['url' => $image->url]);
    // }

    // Delete all images from the database
    Image::truncate();

    return redirect()->back()->with('success', 'All images deleted successfully!');
}
public function export()
{
    return Excel::download(new ImagesExport, 'images.xlsx');
}

}
