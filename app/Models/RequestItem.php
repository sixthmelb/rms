<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'item_number',
        'description',
        'specification',
        'quantity',
        'unit_of_measurement',
        'remarks'
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
