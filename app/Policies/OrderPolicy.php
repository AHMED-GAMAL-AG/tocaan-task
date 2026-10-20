<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Users only ever see their own orders. Someone else's order answers 404,
 * not 403, so ids cannot be probed for existence.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    public function update(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    public function delete(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    public function pay(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    private function owns(User $user, Order $order): Response
    {
        return $order->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound('Order not found.');
    }
}
