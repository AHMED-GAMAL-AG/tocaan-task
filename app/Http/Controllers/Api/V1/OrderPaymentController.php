<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ListPaymentsRequest;
use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Payments nested under a single order.
 */
class OrderPaymentController extends Controller
{
    public function index(ListPaymentsRequest $request, Order $order): AnonymousResourceCollection
    {
        Gate::authorize('view', $order);

        $payments = $order->payments()
            ->filter($request->filters())
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    /**
     * Charge the order through the gateway registered for `payment_method`.
     *
     * Always 201: the attempt is recorded either way, and its `status`
     * (successful / failed) says how it went.
     */
    public function store(ProcessPaymentRequest $request, Order $order, PaymentService $payments): JsonResponse
    {
        $payment = $payments->process($order, $request->paymentMethod(), $request->details());

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
