<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IpPool extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'is_active',
        'description',
        'in_use'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function squidPorts()
    {
        return $this->hasMany(SquidPort::class);
    }
}
