<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\User;
use App\Models\PaymentReference;

class UserCredit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'balance',
        'amount',
        'price',
        'type',
        'description',
        'external_reference',
        'payment_id',
        'status',
        'user_id',
        'asaas_customer',
        'post_action_done_at'
    ];

    protected $casts = [
        'post_action_done_at' => 'datetime',
    ];

    /**
     * Get the user that owns the UserCredit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that owns the UserCredit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentReference()
    {
        return $this->belongsTo(PaymentReference::class, 'external_reference', 'external_reference');
    }
}
