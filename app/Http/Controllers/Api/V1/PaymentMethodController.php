<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    /**
     * The payment methods a client may send as `payment_method`.
     */
    public function __invoke(PaymentGatewayManager $gateways): JsonResponse
    {
        return response()->json(['data' => $gateways->describe()]);
    }
}
