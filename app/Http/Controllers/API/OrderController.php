<?php

namespace App\Http\Controllers\API;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\ProductApproveEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderDetailsResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders with status filter and pagination options.
     *
     * @param  Request  $request  The HTTP request
     * @return Some_Return_Value json Response
     *
     * @throws Some_Exception_Class If something goes wrong
     */
    public function index(Request $request)
    {
        $orderStatus = $request->order_status;

        $page = $request->page;
        $perPage = $request->per_page;
        $skip = ($page * $perPage) - $perPage;

        $customer = auth()->user()->customer;
        $orders = $customer->orders()
            ->when($orderStatus, function ($query) use ($orderStatus) {
                return $query->where('order_status', $orderStatus);
            })->latest('id');

        $total = $orders->count();

        // paginate
        $orders = $orders->when($perPage && $page, function ($query) use ($perPage, $skip) {
            return $query->skip($skip)->take($perPage);
        })->get();

        // return
        return $this->json('orders', [
            'total' => $total,
            'status_wise_orders' => [
                'all' => $customer->orders()->count(),
                'pending' => $customer->orders()->where('order_status', OrderStatus::PENDING->value)->count(),
                'confirm' => $customer->orders()->where('order_status', OrderStatus::CONFIRM->value)->count(),
                'processing' => $customer->orders()->where('order_status', OrderStatus::PROCESSING->value)->count(),
                'on_the_way' => $customer->orders()->where('order_status', OrderStatus::ON_THE_WAY->value)->count(),
                'delivered' => $customer->orders()->where('order_status', OrderStatus::DELIVERED->value)->count(),
                'cancelled' => $customer->orders()->where('order_status', OrderStatus::CANCELLED->value)->count(),
            ],
            'orders' => OrderResource::collection($orders),
        ]);
    }

    /**
     * Store a newly created order in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(OrderRequest $request)
    {
        $isBuyNow = $request->is_buy_now ?? false;

        $carts = auth()->user()->customer->carts()->whereIn('shop_id', $request->shop_ids)->where('is_buy_now', $isBuyNow)->get();

        if ($carts->isEmpty()) {
            return $this->json('Sorry shop cart is empty', [], 422);
        }

        $toUpper = strtoupper($request->payment_method);
        $paymentMethods = PaymentMethod::cases();

        $paymentMethod = $paymentMethods[array_search($toUpper, array_column(PaymentMethod::cases(), 'name'))];

        // Store the order
        $payment = OrderRepository::storeByrequestFromCart($request, $paymentMethod, $carts);

        $paymentUrl = null;
        if ($paymentMethod->name != 'CASH') {
            $paymentUrl = route('order.payment', ['payment' => $payment, 'gateway' => $request->payment_method]);
        }

        // $message = "New order placed!";
        // try {
        //     ProductApproveEvent::dispatch($message);
        // } catch (\Throwable $th) {}

        // $data = (object) [
        //     'title' => $message,
        //     'content' => 'Your product approved from admin',
        //     'url' => '/shop/orders/90/show',
        //     'icon' => 'bi-bag-check-fill',
        //     'type' => 'success',
        //     'shop_id' => $product->shop_id,
        // ];

        // store notification
        // NotificationRepository::storeByRequest($data);

        return $this->json('Order created successfully', [
            'order_payment_url' => $paymentUrl,
        ]);
    }

    /**
     * Again order
     */
    public function reOrder(Request $request)
    {
        // Validate the request
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        // Find the order
        $order = Order::find($request->order_id);

        if ($order->order_status->value == OrderStatus::DELIVERED->value) {

            // Check product quantity
            foreach ($order->products as $product) {
                if ($product->quantity < $product->pivot->quantity) {
                    return $this->json('Sorry, your product quantity out of stock', [], 422);
                }
            }

            // create payment
            $paymentMethod = $order->payments()?->latest('id')->first()->payment_method ?? 'cash';
            $payment = Payment::create([
                'amount' => $order->payable_amount,
                'payment_method' => $paymentMethod,
            ]);

            // re-order
            $order = OrderRepository::reOrder($order);

            // attach payment to order
            $payment->orders()->attach($order->id);

            // payment url
            $paymentUrl = null;
            if ($paymentMethod != 'cash') {
                $paymentUrl = route('order.payment', ['payment' => $payment, 'gateway' => $payment->payment_method]);
            }

            // return
            return $this->json('Re-order created successfully', [
                'order_payment_url' => $paymentUrl,
                'order' => OrderResource::make($order),
            ]);
        }

        return $this->json('Sorry, You can not  re-order because order is not delivered', [], 422);
    }

    /**
     * Show the order details.
     *
     * @param  Request  $request  The request object
     */
    public function show(Request $request)
    {
        // Validate the request
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        // Find the order
        $order = Order::find($request->order_id);

        return $this->json('order details', [
            'order' => OrderDetailsResource::make($order),
        ]);
    }

    /**
     * Cancel the order.
     */
    public function cancel(Request $request)
    {
        // Validate the request
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        // Find the order
        $order = Order::find($request->order_id);

        if ($order->order_status->value == OrderStatus::PENDING->value) {

            // update order status
            $order->update([
                'order_status' => OrderStatus::CANCELLED->value,
            ]);

            foreach ($order->products as $product) {
                $qty = $product->pivot->quantity;

                $product->update(['quantity' => $product->quantity + $qty]);

                $flashsale = $product->flashSales?->first();
                $flashsaleProduct = null;

                if ($flashsale) {
                    $flashsaleProduct = $flashsale?->products()->where('id', $product->id)->first();

                    if ($flashsaleProduct && $product->pivot?->price) {
                        if ($flashsaleProduct->pivot->sale_quantity >= $qty && ($product->pivot?->price == $flashsaleProduct->pivot->price)) {
                            $flashsale->products()->updateExistingPivot($product->id, [
                                'sale_quantity' => $flashsaleProduct->pivot->sale_quantity - $qty,
                            ]);
                        }
                    }
                }
            }

            return $this->json('Order cancelled successfully', [
                'order' => OrderResource::make($order),
            ]);
        }

        return $this->json('Sorry, order cannot be cancelled because it is not pending', [], 422);
    }

    public function payment(Order $order, $paymentMethod = null)
    {
        if ($paymentMethod != 'cash' && $paymentMethod != null) {

            $payment = Payment::create([
                'amount' => $order->payable_amount,
                'payment_method' => $paymentMethod,
            ]);

            $payment->orders()->attach($order->id);

            $paymentUrl = route('order.payment', ['payment' => $payment, 'gateway' => $payment->payment_method]);

            return $this->json('Payment created', [
                'order_payment_url' => $paymentUrl,
            ]);

            // $payment = $order->payments()?->first();

            // if ($payment->payment_method != $paymentMethod) {

            //     $order->update([
            //         'payment_method' => $paymentMethod,
            //     ]);

            //     $orders = $payment->orders()->where('order_status', '!=', OrderStatus::CANCELLED->value)->where('payment_status', PaymentStatus::PENDING->value)->get();

            //     $payment->update([
            //         'payment_method' => $paymentMethod,
            //         'amount' => $orders->sum('payable_amount'),
            //     ]);

            //     $payment->orders()->sync($orders);

            //     $paymentUrl = route('order.payment', ['payment' => $payment, 'gateway' => $payment->payment_method]);

            //     return $this->json('Payment created', [
            //         'order_payment_url' => $paymentUrl,
            //         'order' => OrderResource::make($order),
            //     ]);
            // }

            // $payment = Payment::create([
            //     'amount' => $order->payable_amount,
            //     'payment_method' => $paymentMethod,
            // ]);
        }

        return $this->json('Sorry, You can not  re-payment because payment is CASH', [], 422);
    }
}
