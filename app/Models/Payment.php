<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'method',
    'status',
    'amount',
    'currency',
    'transaction_reference',
    'failure_reason',
    'meta',
    'processed_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'meta' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->whereHas('order', fn (Builder $query) => $query->whereBelongsTo($user));
    }

    /**
     * @param  Builder<Payment>  $query
     * @param  array{status?: string, method?: string, order_id?: int}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['method'] ?? null, fn (Builder $query, string $method) => $query->where('method', $method))
            ->when($filters['order_id'] ?? null, fn (Builder $query, int|string $orderId) => $query->where('order_id', $orderId));
    }
}
