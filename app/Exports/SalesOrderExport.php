<?php

namespace App\Exports;

use App\Models\SalesOrder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesOrderExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function query()
    {
        return SalesOrder::query();
    }

    public function headings(): array
    {
        return [
            'BU / CABANG',
            'NOMOR SO',
            'KODE CUSTOMER',
            'GUDANG (WH)',
            'KODE BUKU / ITEM',
            'QTY ORDER',
            'QTY OUTSTANDING',
            'TANGGAL SO',
            'TERAKHIR SYNC',
        ];
    }

    public function map($so): array
    {
        return [
            $so->bu,
            $so->sales_id,
            $so->accountnum,
            $so->inventlocationid,
            $so->itemid,
            $so->qty_order,
            $so->qty_outstanding,
            $so->created_date,
            $so->updated_at ? $so->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }
}