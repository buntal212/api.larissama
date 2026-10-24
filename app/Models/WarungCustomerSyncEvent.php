<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarungCustomerSyncEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
