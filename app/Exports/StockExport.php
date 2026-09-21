<?php

namespace App\Exports;

use App\Models\Stock;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function query()
    {
        return Stock::query();
    }

    public function headings(): array
    {
        return [
            'BU / CABANG',
            'GUDANG (WH)',
            'KODE BUKU / ITEM',
            'STOK FISIK',
            'STOK TRANSIT',
            'PO OUTSTANDING',
            'TERAKHIR SYNC',
        ];
    }

    public function map($stock): array
    {
        return [
            $stock->bu,
            $stock->inventlocationid,
            $stock->itemid,
            $stock->stok_physical,
            $stock->stok_transit,
            $stock->po_outstanding,
            $stock->updated_at ? $stock->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }
}