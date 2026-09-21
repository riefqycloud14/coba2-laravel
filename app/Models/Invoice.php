<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_no',
        'bu',
        'thlap',
        'kodcab',
        'kodbuk',
        'cvaccount',
        'agr_invoiceidreturn',
        'agr_invoicedatereturn',
        'tglfak',
        'blfak',
        'hrfak',
        'tglax',
        'type',
        'accountnum',
        'namlan',
        'sekolah',
        'pricegroupid',
        'soid',
        'salesunit',
        'kodsal',
        'nosp',
        'sumberdana',
        'program',
        'alasan',
        'customerref',
        'payment',
        'kwantum',
        'harga',
        'subtot',
        'total',
        'discpercent',
        'discamount',
        'discrp',
        'tr',
        'trnum',
        'totaltr',
        'trbiaya',
        'totalbiaya',
        'netto',
        'nettbiaya',
        'orderaccount',
        'swa',
        'periode',
    ];
}