<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InvoicesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected string $tglAwal;
    protected string $tglAkhir;

    public function __construct(string $tglAwal, string $tglAkhir)
    {
        $this->tglAwal  = $tglAwal;
        $this->tglAkhir = $tglAkhir;
    }

    public function query()
    {
        return Invoice::query()
            ->whereDate('tglfak', '>=', $this->tglAwal)
            ->whereDate('tglfak', '<=', $this->tglAkhir)
            ->orderBy('tglfak', 'asc');
    }

    public function headings(): array
    {
        return [
            'No Invoice',
            'BU / Cabang',
            'Tgl Faktur',
            'Jenis Transaksi',
            'Kode Pelanggan',
            'Sales Unit',
            'Sumber Dana',
            'Program',
            'Klasifikasi (SWA/NSW)',
            'Periode',
            'Subtotal (Rp)',
            'Total Bruto (Rp)',
            'Diskon TR (%)',
            'Total TR (Rp)',
            'TRNUM (Commission Base)',
            'TR Biaya (%)',
            'Total Biaya (Rp)',
            'Total Biaya 2 (Rp)',
            'Netto (Rp)',
            'Nett Biaya (Rp)',
            // Field Olahan Tambahan FoxPro
            'Judul Buku (Judbuk)',
            'Nama Karangan (Namrang)',
            'Jenjang',
            'Tgl Terbit',
            'Thn Terbit',
            'Grup',
            'Nama Langganan (Namlan)',
            'Sekolah',
            'Nama Sales (Namsal)',
            'Created Date',
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_no ?? $invoice->no_factur,
            $invoice->bu == '61' ? '61 (Pontianak)' : ($invoice->bu == '59' ? '59 (Samarinda)' : $invoice->bu),
            $invoice->tglfak,
            $invoice->type,
            $invoice->accountnum ?? $invoice->custaccount,
            $invoice->salesunit,
            $invoice->sumberdana,
            $invoice->program,
            $invoice->swa,
            $invoice->periode,
            $invoice->subtot,
            $invoice->total,
            $invoice->tr,
            $invoice->totaltr,
            $invoice->trnum ?? $invoice->trnumtr,
            $invoice->trbiaya,
            $invoice->totalbiaya,
            $invoice->totalbiaya2,
            $invoice->netto,
            $invoice->nettbiaya,
            // Mapping Field Olahan Tambahan FoxPro
            $invoice->judbuk,
            $invoice->namrang,
            $invoice->jenjang,
            $invoice->tglterbit,
            $invoice->thnterbit,
            $invoice->grup,
            $invoice->namlan,
            $invoice->sekolah,
            $invoice->namsal,
            $invoice->createddat,
        ];
    }
}