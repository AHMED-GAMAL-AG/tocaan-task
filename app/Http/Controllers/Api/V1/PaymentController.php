<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ListPaymentsRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function index(ListPaymentsRequest $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->ownedBy($request->user())
            ->filter($request->filters())
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    public function show(Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);

        return new PaymentResource($payment);
    }
}
