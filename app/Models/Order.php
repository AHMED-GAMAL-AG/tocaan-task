<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_name',
    'customer_email',
    'customer_phone',
    'shipping_address',
    'status',
    'total',
    'notes',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'total' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function hasPayments(): bool
    {
        return $this->payments()->exists();
    }

    /**
     * Whether a payment that is pending or successful already exists,
     * which means another attempt must not be started.
     */
    public function hasActivePayment(): bool
    {
        return $this->payments()
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Successful])
            ->exists();
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopeWithStatus(Builder $query, ?OrderStatus $status): void
    {
        $query->when($status, fn (Builder $query) => $query->where('status', $status));
    }
}
