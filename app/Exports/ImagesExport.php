<?php

namespace App\Exports;

use App\Models\Image;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Str;

class ImagesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Image::select('filename', 'url')
            ->get()
            ->map(function ($image) {
                $image->url = Str::replaceFirst('https://app.talentindustrial.com/plumber/uploads/', '', $image->url);
                return $image;
            });
    }

    public function headings(): array
    {
        return ["Filename", "URL"];
    }
}
