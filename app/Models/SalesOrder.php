<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'bu',
        'sales_id',
        'accountnum',
        'inventlocationid',
        'itemid',
        'qty_order',
        'qty_outstanding',
        'created_date',
    ];
}