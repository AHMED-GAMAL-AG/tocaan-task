<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): Response
    {
        return $payment->order->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound('Payment not found.');
    }
}
