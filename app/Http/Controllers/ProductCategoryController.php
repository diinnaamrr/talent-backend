<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ProductCategoryController extends Controller
{
    public function showUploadForm()
    {
        return view('product_categories.upload');
    }
	
public function downloadTemplate()
{
    // Path to the product category template file
    $filePath = public_path('templates/product_category_template.xlsx');
    return response()->download($filePath);
}

    public function upload(Request $request)
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $file = $request->file('file');

        // Load Excel file
        $data = Excel::toCollection(null, $file)->first();

        if ($data->isEmpty()) {
            return back()->with('error', 'The file is empty or invalid.');
        }

        // Process each row in the Excel file
        foreach ($data as $index => $row) {
            $cat1 = isset($row[0]) ? trim($row[0]) : null; // APP Cat 1
            $cat2 = isset($row[1]) ? trim($row[1]) : null; // APP Cat 2
            $cat3 = isset($row[2]) ? trim($row[2]) : null; // APP Cat 3
            $cat4 = isset($row[3]) ? trim($row[3]) : null; // APP Cat 4
            $cat5 = isset($row[4]) ? trim($row[4]) : null; // APP Cat 5
            $productName = isset($row[5]) ? trim($row[5]) : null; // Product name
			$points = isset($row[6]) ? (float) trim($row[6]) : 0.00;
            $image = isset($row[7]) ? trim($row[7]) : null; // Img No

            // Skip the row if Cat. 1 or product name is missing
            if (empty($cat1) || empty($productName)) {
                continue;
            }

            // Handle the hierarchy of categories
            $parentId = null;

            // Process Cat. 1 to Cat. 5, skipping empty or "0" categories
            foreach ([$cat1, $cat2, $cat3, $cat4, $cat5] as $category) {
                if ($category && $category !== '0') {
                    // Check if the category already exists
                    $existingCategory = DB::table('plumber_categories')
                        ->where('name', $category)
                        ->where('parent_id', $parentId)
                        ->first();

                    if (!$existingCategory) {
                        // Create the category if it doesn't exist
                        $newCategoryId = DB::table('plumber_categories')->insertGetId([
                            'name' => $category,
                            'parent_id' => $parentId,
                            'image' => null, // No image for categories
                            'points' => 0, // Points only apply to products
                            'product_flag' => 0, // Categories are not products
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $parentId = $newCategoryId; // Set the parent ID for the next category level
                    } else {
                        $parentId = $existingCategory->id; // Use the existing category ID as parent
                    }
                }
            }

            // Insert the product under the last category level
            DB::table('plumber_categories')->insert([
                'name' => $productName, // Product name
                'parent_id' => $parentId,
                'image' => $image,
                'points' => $points, // Points from the Excel file
                'product_flag' => 1, // Products always have product_flag = 1
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Products and categories have been successfully imported.');
    }
}