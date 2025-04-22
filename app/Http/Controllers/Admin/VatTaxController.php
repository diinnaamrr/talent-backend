<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VatTaxType;
use App\Http\Controllers\Controller;
use App\Http\Requests\VatTaxRequest;
use App\Models\VatTax;
use App\Repositories\VatTaxRepository;
use Illuminate\Http\Request;

class VatTaxController extends Controller
{
    public function index()
    {
        $vatTaxes = VatTaxRepository::query()->where('type', '!=', VatTaxType::ORDERBASE->value)->paginate(20);

        $orderBaseTax = VatTaxRepository::getOrderBaseTax();

        $types = VatTaxType::cases();

        return view('admin.vattax.index', compact('vatTaxes', 'orderBaseTax', 'types'));
    }

    public function store(VatTaxRequest $request)
    {
        VatTaxRepository::storeByRequest($request);

        return to_route('admin.vatTax.index')->withSuccess(__('Vat tax created successfully'));
    }

    public function update(VatTax $vatTax, VatTaxRequest $request)
    {
        VatTaxRepository::updateByRequest($vatTax, $request);

        return to_route('admin.vatTax.index')->withSuccess(__('Vat tax updated successfully'));
    }

    public function toggle(VatTax $vatTax)
    {
        VatTaxRepository::toggle($vatTax);

        return to_route('admin.vatTax.index')->withSuccess(__('Status Updated Successfully'));
    }

    public function orderTaxUpdate(Request $request)
    {
        if ($request->type == VatTaxType::ORDERBASE->value) {
            $request->validate([
                'percentage' => 'required|numeric|min:0|max:100',
                'deduction' => 'required',
            ]);
        }

        VatTaxRepository::updateOrderBaseTax($request);

        return to_route('admin.vatTax.index', 'type=order base')->withSuccess(__('Updated Successfully'));
    }

    public function destroy(VatTax $vatTax)
    {
        if($vatTax->products->isEmpty()) {
            $vatTax->delete();
            return to_route('admin.vatTax.index')->withSuccess(__('Deleted Successfully'));
        }

        return to_route('admin.vatTax.index')->withError(__('Vat tax cannot be deleted because it has products'));
    }
}
