<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'identifier',
        'external_reference',
        'price',
        'collector_id',
        'client_id',
        'init_point',
        'type',
        'gateway',
        'status',
        'payment_id',
        'asaas_customer'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'updated_at' => 'datetime'
    ];

      /**
     * Get the user that owns the UserCredit
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function userCredit()
    {
        return $this->hasOne(UserCredit::class, 'external_reference', 'external_reference');
    }
}
