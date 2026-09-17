<?php

namespace App\Filament\Pages;

use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exports\InvoicesExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Actions\Action;
use App\Models\Invoice;
use App\Services\AxaptaSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Exception;

class SyncAxapta extends Page implements Forms\Contracts\HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'Integrasi Data';
    protected static ?string $title = 'Penarikan Data Antar Cabang (Axapta)';
    protected static string $view = 'filament.pages.sync-axapta';

    public ?array $data = [];
    public bool $isProcessed = false;
    public string $tglAwalShow = '';
    public string $tglAkhirShow = '';
    public array $summaryStat = [];

    public function mount(): void
    {
        $this->form->fill([
            'tgl_awal' => now()->startOfMonth()->format('Y-m-d'),
            'tgl_akhir' => now()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Filter Periode Penarikan')
                    ->description('Pilih rentang tanggal invoice yang akan ditarik dari cabang Pontianak (BU 61) dan Samarinda (BU 59).')
                    ->schema([
                        Forms\Components\DatePicker::make('tgl_awal')
                            ->label('Tanggal Awal')
                            ->required(),
                        Forms\Components\DatePicker::make('tgl_akhir')
                            ->label('Tanggal Akhir')
                            ->required(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(AxaptaSyncService $syncService)
    {
        $formData = $this->form->getState();

        try {
            $syncService->syncTransactionAndCustomer(
                $formData['tgl_awal'],
                $formData['tgl_akhir']
            );

            $this->isProcessed = true;
            $this->tglAwalShow = $formData['tgl_awal'];
            $this->tglAkhirShow = $formData['tgl_akhir'];

            // Gunakan whereDate agar pencarian tanggal di MySQL akurat
            $query = Invoice::whereDate('tglfak', '>=', $formData['tgl_awal'])
                            ->whereDate('tglfak', '<=', $formData['tgl_akhir']);

            $this->summaryStat = [
                'total_count' => $query->count(),
                'total_bruto' => $query->sum('total'),
                'total_netto' => $query->sum('netto'),
                'total_sw'    => (clone $query)->where('swa', 'SW')->count(),
                'total_nsw'   => (clone $query)->where('swa', 'NSW')->count(),
            ];

            $this->resetTable();

            Notification::make()
                ->title('Penarikan Data Berhasil!')
                ->body("Proses selesai. Ditemukan {$this->summaryStat['total_count']} faktur.")
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
                if (!$this->isProcessed) {
                    return Invoice::query()->whereRaw('1 = 0');
                }

                return Invoice::query()
                    ->whereDate('tglfak', '>=', $this->tglAwalShow)
                    ->whereDate('tglfak', '<=', $this->tglAkhirShow)
                    ->orderBy('tglfak', 'desc');
            })
            ->headerActions([
                Action::make('downloadExcel')
                    ->label('Download Excel / CSV Rincian')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('success')
                    ->action(function (): StreamedResponse {
                        $fileName = "Rincian_Transaksi_A2A_{$this->tglAwalShow}_sd_{$this->tglAkhirShow}.csv";

                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');

                            // UTF-8 BOM agar terbaca rapi di Excel
                            fputs($handle, "\xEF\xBB\xBF");

                            // Header Kolom Lengkap
                            fputcsv($handle, [
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
                            ]);

                            // Stream Data
                            Invoice::query()
                                ->whereDate('tglfak', '>=', $this->tglAwalShow)
                                ->whereDate('tglfak', '<=', $this->tglAkhirShow)
                                ->orderBy('tglfak', 'asc')
                                ->chunk(500, function ($invoices) use ($handle) {
                                    foreach ($invoices as $inv) {
                                        fputcsv($handle, [
                                            $inv->invoice_no ?? $inv->no_factur,
                                            $inv->bu == '61' ? '61 (Pontianak)' : ($inv->bu == '59' ? '59 (Samarinda)' : $inv->bu),
                                            $inv->tglfak,
                                            $inv->type,
                                            $inv->accountnum ?? $inv->custaccount,
                                            $inv->salesunit,
                                            $inv->sumberdana,
                                            $inv->program,
                                            $inv->swa,
                                            $inv->periode,
                                            $inv->subtot,
                                            $inv->total,
                                            $inv->tr,
                                            $inv->totaltr,
                                            $inv->trnum ?? $inv->trnumtr,
                                            $inv->trbiaya,
                                            $inv->totalbiaya,
                                            $inv->totalbiaya2,
                                            $inv->netto,
                                            $inv->nettbiaya,
                                            $inv->judbuk,
                                            $inv->namrang,
                                            $inv->jenjang,
                                            $inv->tglterbit,
                                            $inv->thnterbit,
                                            $inv->grup,
                                            $inv->namlan,
                                            $inv->sekolah,
                                            $inv->namsal,
                                            $inv->createddat,
                                        ]);
                                    }
                                });

                            fclose($handle);
                        }, $fileName, [
                            'Content-Type' => 'text/csv; charset=UTF-8',
                            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                        ]);
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')
                    ->label('No. Invoice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bu')
                    ->label('BU')
                    ->formatStateUsing(fn ($state) => $state == '61' ? '61 (PTK)' : ($state == '59' ? '59 (SMD)' : $state)),
                Tables\Columns\TextColumn::make('tglfak')
                    ->label('Tgl Faktur')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Jenis')
                    ->colors([
                        'success' => 'SALES',
                        'danger' => 'RETUR',
                    ]),
                Tables\Columns\TextColumn::make('accountnum')
                    ->label('Kode Pelanggan')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('swa')
                    ->label('SWA / NSW')
                    ->colors([
                        'primary' => 'SW',
                        'warning' => 'NSW',
                    ]),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total Bruto')
                    ->money('IDR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('netto')
                    ->label('Netto (Hasil Olahan)')
                    ->money('IDR', true)
                    ->sortable(),

                // Kolom Tambahan (Disembunyikan secara default, dapat diaktifkan melalui opsi kolom di web)
                Tables\Columns\TextColumn::make('trnum')
                    ->label('TRNUM')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('namlan')
                    ->label('Nama Langganan')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sekolah')
                    ->label('Sekolah')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('namsal')
                    ->label('Nama Sales')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('judbuk')
                    ->label('Judul Buku')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('jenjang')
                    ->label('Jenjang')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('createddat')
                    ->label('Created Date')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated([10, 25, 50, 100]);
    }
}