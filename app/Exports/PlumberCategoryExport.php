<?php

namespace App\Exports;

use App\Models\PlumberCategory;
use Maatwebsite\Excel\Concerns\FromArray;

class ProductCategoriesExport implements FromArray
{
    public function array(): array
    {
        // Define Excel headers
        $data = [['Cat. 1', 'Cat. 2', 'Cat. 3', 'Cat. 4', 'Cat. 5', 'Img No']];

        // Fetch all categories
        $categories = PlumberCategory::all(['id', 'name', 'parent_id', 'image']);

        // Function to build hierarchy
        $structuredData = $this->buildCategoryTree($categories);

        // Add structured data to export array
        foreach ($structuredData as $row) {
            $data[] = [
                $row['cat1'] ?? '',
                $row['cat2'] ?? '',
                $row['cat3'] ?? '',
                $row['cat4'] ?? '',
                $row['cat5'] ?? '',
                $row['image'] ?? '',
            ];
        }

        return $data;
    }

    private function buildCategoryTree($categories)
    {
        $tree = [];

        // Create a map of categories by id
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->id] = [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'image' => $category->image,
                'children' => [],
            ];
        }

        // Build the hierarchy by linking parent and children
        foreach ($categoryMap as &$category) {
            if ($category['parent_id'] && isset($categoryMap[$category['parent_id']])) {
                $categoryMap[$category['parent_id']]['children'][] = &$category;
            }
        }
        unset($category); // Avoid reference issues

        // Extract only root categories
        foreach ($categoryMap as $category) {
            if (!$category['parent_id']) {
                $tree = array_merge($tree, $this->flattenCategoryTree($category));
            }
        }

        return $tree;
    }

    private function flattenCategoryTree($category, $levels = [])
    {
        $levels[] = $category['name'];

        $result = [];
        if (!empty($category['children'])) {
            foreach ($category['children'] as $child) {
                $result = array_merge($result, $this->flattenCategoryTree($child, $levels));
            }
        } else {
            // Fill up to 5 category levels
            while (count($levels) < 5) {
                $levels[] = '';
            }

            $result[] = [
                'cat1' => $levels[0] ?? '',
                'cat2' => $levels[1] ?? '',
                'cat3' => $levels[2] ?? '',
                'cat4' => $levels[3] ?? '',
                'cat5' => $levels[4] ?? '',
                'image' => $category['image'] ?? '',
            ];
        }

        return $result;
    }
}
