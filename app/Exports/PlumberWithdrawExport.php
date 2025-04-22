<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use App\Models\PlumberWithdraw;
use Maatwebsite\Excel\Concerns\FromCollection;

class PlumberWithdrawExport implements FromCollection, WithHeadings, WithTitle
{
    protected $withdrawals;

    public function __construct($withdrawals)
    {
        $this->withdrawals = $withdrawals;
    }

    // Return the data to be exported
    public function collection()
    {
        return $this->withdrawals->map(function ($withdrawal) {
            return [
                'ID' => $withdrawal->id,
                'Requestor Name' => $withdrawal->plumber->user ? $withdrawal->plumber->user->name : 'N/A', // Get user name from the relation
                'Phone' => $withdrawal->plumber->user ? $withdrawal->plumber->user->phone : 'N/A', // Example for phone, assuming it's available in the user model
                'Amount' => $withdrawal->amount,
                'Transaction Type' => $withdrawal->transaction_type,
                'Request Date' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                'Status' => $withdrawal->status,
            ];
        });
    }

    // Set the headings for the Excel sheet
    public function headings(): array
    {
        return [
            'ID',
            'Requestor Name',
            'Phone',
            'Amount',
            'Transaction Type',
            'Request Date',
            'Status',
        ];
    }

    // Set the title of the sheet (optional)
    public function title(): string
    {
        return 'Withdrawals';
    }
}

