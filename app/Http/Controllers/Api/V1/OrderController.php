<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ListOrdersRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->ownedBy($request->user())
            ->withStatus($request->status())
            ->with('items')
            ->withCount('payments')
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->user(), $request->validated());

        return (new OrderResource($order->loadCount('payments')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load('items')->loadCount('payments'));
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->update($order, $request->validated());

        return new OrderResource($order->loadCount('payments'));
    }

    public function destroy(Order $order): Response
    {
        Gate::authorize('delete', $order);

        $this->orders->delete($order);

        return response()->noContent();
    }
}
