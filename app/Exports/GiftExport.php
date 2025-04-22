<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Models\PlumberReceivedGift;
use Maatwebsite\Excel\Concerns\FromCollection;

class GiftExport implements FromCollection, WithHeadings, WithTitle
{
	protected $gifts;

    public function __construct($gifts)
    {
        $this->gifts = $gifts;
    }

    // Return the data to be exported
    public function collection()
    {
        return $this->gifts->map(function ($gift) {
            return [
                'ID' => $gift->id,
            	'User Name' => $gift->user->name,
                'Gift Name' => $gift->plumber_gift->name ? $gift->plumber_gift->name : 'N/A', 
                'Points Required' => $gift->plumber_gift->points_required ? $gift->plumber_gift->points_required : 'N/A',
                'Created At' => $gift->createdAt,
                'Updated At' => $gift->updatedAt,
                'Image' => $gift->plumber_gift->image,
            ];
        });
    }

    // Set the headings for the Excel sheet
    public function headings(): array
    {
        return [
            'ID',
            'User Name',
            'Gift Name',
            'Points Required',
            'Created At',
            'Updated At',
            'Image',
        ];
    }

    // Set the title of the sheet (optional)
    public function title(): string
    {
        return 'Gifts';
    }
}
