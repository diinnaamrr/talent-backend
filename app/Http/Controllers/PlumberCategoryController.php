<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class PlumberCategoryController extends Controller
{
    public function showUploadForm()
    {
        return view('plumber_categories.upload');
    }
public function downloadTemplate()
{
    // Path to the template file
    $filePath = public_path('templates/plumber_category_template.xlsx');
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
            $cat1 = isset($row[0]) ? trim($row[0]) : null; // Cat. 1
            $cat2 = isset($row[1]) ? trim($row[1]) : null; // Cat. 2
            $cat3 = isset($row[2]) ? trim($row[2]) : null; // Cat. 3
            $cat4 = isset($row[3]) ? trim($row[3]) : null; // Cat. 4
            $cat5 = isset($row[4]) ? trim($row[4]) : null; // Cat. 5
            $image = isset($row[5]) ? trim($row[5]) : null; // Img No

            // Skip the row if Cat. 1 is missing
            if (empty($cat1)) {
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
                            'image' => $image,
                            'points' => 0,
                            'product_flag' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $parentId = $newCategoryId; // Set the parent ID for the next category level
                    } else {
                        $parentId = $existingCategory->id; // Use the existing category ID as parent
                    }
                }
            }
        }

        return back()->with('success', 'Categories have been successfully imported.');
    }
}