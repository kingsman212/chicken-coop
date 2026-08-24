<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedingLog extends Model
{
    protected $fillable = [
        'feeding_schedule_id',
        'schedule_label',
        'source',
        'status',
        'portion_grams',
        'fed_at',
    ];

    protected $casts = [
        'fed_at' => 'datetime',
        'portion_grams' => 'integer',
    ];

    public function schedule()
    {
        return $this->belongsTo(FeedingSchedule::class, 'feeding_schedule_id');
    }
}
