<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingSchedule extends Model
{
    protected $fillable = [
        'label',
        'time',
        'portion_grams',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'portion_grams' => 'integer',
    ];

    public function logs()
    {
        return $this->hasMany(FeedingLog::class);
    }
}
