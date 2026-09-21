<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'bu',
        'inventlocationid',
        'itemid',
        'stok_physical',
        'stok_transit',
        'po_outstanding',
    ];
}