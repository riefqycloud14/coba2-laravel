<?php

namespace App\Filament\Pages;

use App\Models\Invoice;
use App\Services\AxaptaSyncService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class RawInvoiceHistory extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Integrasi Data';
    protected static ?string $title = 'History & Penarikan Data Utama';
    protected static string $view = 'filament.pages.raw-invoice-history';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tgl_awal'  => now()->startOfMonth()->format('Y-m-d'),
            'tgl_akhir' => now()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filter Periode Penarikan Data Utama')
                    ->description('Pilih rentang tanggal penarikan. Data akan di-upsert ke Data Master Invoices.')
                    ->schema([
                        Forms\Components\DatePicker::make('tgl_awal')
                            ->label('Tanggal Awal')
                            ->live()
                            ->required(),
                        Forms\Components\DatePicker::make('tgl_akhir')
                            ->label('Tanggal Akhir')
                            ->live()
                            ->required(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(AxaptaSyncService $syncService)
    {
        $formData = $this->form->getState();

        try {
            // Set time limit agar tidak timeout saat tarik data besar dari Axapta
            set_time_limit(600);

            // 1. Tarik Data Transaksi Invoice (IP 10.1.1.64)
            $syncService->syncTransactionAndCustomer(
                $formData['tgl_awal'],
                $formData['tgl_akhir']
            );

            // 2. Tarik Master Customer (IP 10.1.1.64)
            $syncService->syncCustomers();

            $this->resetTable();

            Notification::make()
                ->title('Penarikan Data Berhasil!')
                ->body('Data Invoice & Master Customer berhasil diperbarui.')
                ->success()
                ->send();

        } catch (Exception $e) {
            Notification::make()
                ->title('Gagal Penarikan Data')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $tglAwal  = $this->data['tgl_awal'] ?? now()->startOfMonth()->format('Y-m-d');
                $tglAkhir = $this->data['tgl_akhir'] ?? now()->format('Y-m-d');

                // OPTIMASI 1: Gunakan whereBetween alih-alih whereDate
                return Invoice::query()
                    ->whereBetween('tglfak', [$tglAwal . ' 00:00:00', $tglAkhir . ' 23:59:59'])
                    ->orderBy('tglfak', 'asc');
            })
            ->headerActions([
                Action::make('downloadCsv')
                    ->label('Download Data Utama (CSV/Excel)')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('success')
                    ->action(function (): StreamedResponse {
                        // OPTIMASI 2: Hilangkan batas waktu eksekusi PHP untuk ekspor
                        set_time_limit(0);

                        $tglAwal  = $this->data['tgl_awal'] ?? now()->startOfMonth()->format('Y-m-d');
                        $tglAkhir = $this->data['tgl_akhir'] ?? now()->format('Y-m-d');
                        $fileName = "Data_Utama_Lengkap_{$tglAwal}_sd_{$tglAkhir}.csv";

                        return response()->streamDownload(function () use ($tglAwal, $tglAkhir) {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF"); // Format BOM UTF-8 untuk Excel

                            // Header CSV Lengkap
                            fputcsv($handle, [
                                'No Invoice', 
                                'BU', 
                                'Tgl Faktur', 
                                'Tgl Axapta', 
                                'Tipe',
                                'Kode Pelanggan', 
                                'Nama Pelanggan', 
                                'Sekolah',
                                'Kota',
                                'Kode Buku',
                                'Nama Buku',
                                'Pengarang',
                                'Kuantitas (Qty)',
                                'Harga Satuan',
                                'Sales Unit', 
                                'Sumber Dana', 
                                'Program', 
                                'SWA', 
                                'Subtotal', 
                                'Total Bruto', 
                                'TR (%)', 
                                'Total TR', 
                                'TR Biaya', 
                                'Total Biaya', 
                                'Netto', 
                                'Nett Biaya'
                            ]);

                            // OPTIMASI 3: Gunakan cursor() alih-alih chunk() agar bebas dari Offset overhead
                            $query = DB::table('invoices as i')
                                ->leftJoin('customers as c', function ($join) {
                                    $join->on('i.accountnum', '=', 'c.accountnum')
                                         ->on('i.bu', '=', 'c.dimension');
                                })
                                ->leftJoin('items as it', 'i.kodbuk', '=', 'it.itemid')
                                ->select([
                                    'i.invoice_no', 'i.bu', 'i.tglfak', 'i.tglax', 'i.type',
                                    'i.accountnum', 'c.name as customer_name', 'c.agr_schoolid as sekolah', 'c.city',
                                    'i.kodbuk', 'it.itemname as nama_buku', 'it.agr_pengarang as pengarang',
                                    'i.kwantum', 'i.harga',
                                    'i.salesunit', 'i.sumberdana', 'i.program', 
                                    'i.swa', 'i.subtot', 'i.total', 'i.tr', 'i.totaltr', 
                                    'i.trbiaya', 'i.totalbiaya', 'i.netto', 'i.nettbiaya'
                                ])
                                ->whereBetween('i.tglfak', [$tglAwal . ' 00:00:00', $tglAkhir . ' 23:59:59'])
                                ->orderBy('i.tglfak', 'asc');

                            foreach ($query->cursor() as $r) {
                                fputcsv($handle, [
                                    '="' . ($r->invoice_no ?? '') . '"',
                                    $r->bu ?? '',
                                    $r->tglfak ?? '',
                                    $r->tglax ?? '',
                                    $r->type ?? '',
                                    '="' . ($r->accountnum ?? '') . '"',
                                    $r->customer_name ?? '',
                                    $r->sekolah ?? '',
                                    $r->city ?? '',
                                    '="' . ($r->kodbuk ?? '') . '"',
                                    $r->nama_buku ?? '',
                                    $r->pengarang ?? '',
                                    number_format($r->kwantum ?? 0, 0, '', ''),
                                    number_format($r->harga ?? 0, 0, '', ''),
                                    $r->salesunit ?? '',
                                    $r->sumberdana ?? '',
                                    $r->program ?? '',
                                    $r->swa ?? '',
                                    number_format($r->subtot ?? 0, 0, '', ''),
                                    number_format($r->total ?? 0, 0, '', ''),
                                    number_format($r->tr ?? 0, 2, '.', ''),
                                    number_format($r->totaltr ?? 0, 0, '', ''),
                                    number_format($r->trbiaya ?? 0, 2, '.', ''),
                                    number_format($r->totalbiaya ?? 0, 0, '', ''),
                                    number_format($r->netto ?? 0, 0, '', ''),
                                    number_format($r->nettbiaya ?? 0, 0, '', ''),
                                ]);
                            }

                            fclose($handle);
                        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')->label('No. Invoice')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('bu')->label('BU')->sortable(),
                Tables\Columns\TextColumn::make('tglfak')->label('Tgl Faktur')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('kodbuk')->label('Kode Buku')->searchable(),
                Tables\Columns\TextColumn::make('accountnum')->label('Kode Pelanggan')->searchable(),
                Tables\Columns\TextColumn::make('total')->label('Total Bruto')->money('IDR', true)->sortable(),
                Tables\Columns\TextColumn::make('netto')->label('Netto')->money('IDR', true)->sortable(),
            ])
            ->paginated([10, 25, 50, 100]);
    }
}