<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;

class SquidPort extends Model
{
    use HasFactory;

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at'       => 'datetime',
        'auto_renovation'  => 'boolean',
    ];

    protected $appends = ['active_license'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (is_null($model->expires_at)) {
                $model->expires_at = now();
            }
        });
    }

    public function getActiveLicenseAttribute()
    {
        return $this->expires_at && Carbon::now()->lt(Carbon::parse($this->expires_at));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
