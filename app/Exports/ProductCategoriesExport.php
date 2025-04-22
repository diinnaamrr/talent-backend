<?php

namespace App\Exports;

use App\Models\PlumberCategory; // Assuming you have a PlumberCategory model
use Maatwebsite\Excel\Concerns\FromCollection;

class ProductCategoriesExport implements FromCollection
{
    public function collection()
    {
        // Fetch the product categories with their relevant data
        return PlumberCategory::where('product_flag', 1)
            ->get(['name', 'parent_id', 'image', 'points']);
    }
}