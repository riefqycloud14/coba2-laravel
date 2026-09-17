<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawInvoice extends Model
{
    use HasFactory;

    // Tambahkan baris ini untuk menegaskan nama tabel di MySQL
    protected $table = 'raw_invoices';

    protected $guarded = [];
}