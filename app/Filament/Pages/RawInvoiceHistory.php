<?php

namespace App\Filament\Pages;

use App\Models\RawInvoice;
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
    public bool $isProcessed = false;
    public string $tglAwalShow = '';
    public string $tglAkhirShow = '';

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
                Forms\Components\Section::make('Filter Periode Penarikan Data Utama')
                    ->description('Tarik seluruh transaksi invoice beserta detail nama pelanggan dan judul buku dari SQL Server Axapta.')
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
            $syncService->pullRawInvoicesWithDetails(
                $formData['tgl_awal'],
                $formData['tgl_akhir']
            );

            $this->isProcessed = true;
            $this->tglAwalShow = $formData['tgl_awal'];
            $this->tglAkhirShow = $formData['tgl_akhir'];

            $this->resetTable();

            Notification::make()
                ->title('Penarikan Data Utama Berhasil!')
                ->body('Data invoice lengkap dengan detail pelanggan & buku berhasil disimpan.')
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
                    return RawInvoice::query()->whereRaw('1 = 0');
                }

                return RawInvoice::query()
                    ->whereDate('tglfak', '>=', $this->tglAwalShow)
                    ->whereDate('tglfak', '<=', $this->tglAkhirShow)
                    ->orderBy('tglfak', 'desc');
            })
            ->headerActions([
                Action::make('downloadCsv')
                    ->label('Download Data Utama (CSV/Excel)')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('success')
                    ->action(function (): StreamedResponse {
                        $fileName = "Data_Utama_Axapta_{$this->tglAwalShow}_sd_{$this->tglAkhirShow}.csv";

                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF");

                            fputcsv($handle, [
                                'No Invoice', 'BU', 'Tgl Faktur', 'Jenis', 'Kode Pelanggan',
                                'Nama Pelanggan', 'Sekolah', 'Kode Buku', 'Judul Buku',
                                'Pengarang', 'Jenjang', 'Mapel', 'Thn Terbit', 'Harga Buku',
                                'Sales Unit', 'Sumber Dana', 'Program', 'Subtotal', 'Total Bruto', 'TR (%)'
                            ]);

                            RawInvoice::query()
                                ->whereDate('tglfak', '>=', $this->tglAwalShow)
                                ->whereDate('tglfak', '<=', $this->tglAkhirShow)
                                ->orderBy('tglfak', 'asc')
                                ->chunk(500, function ($rows) use ($handle) {
                                    foreach ($rows as $r) {
                                        fputcsv($handle, [
                                            $r->invoice_no, $r->bu, $r->tglfak, $r->type,
                                            $r->accountnum, $r->customer_name, $r->sekolah,
                                            $r->kodbuk, $r->judbuk, $r->pengarang, $r->jenjang,
                                            $r->mapel, $r->thnterbit, $r->harga_buku, $r->salesunit,
                                            $r->sumberdana, $r->program, $r->subtot, $r->total, $r->tr
                                        ]);
                                    }
                                });

                            fclose($handle);
                        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')->label('No. Invoice')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('bu')->label('BU'),
                Tables\Columns\TextColumn::make('tglfak')->label('Tgl Faktur')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->label('Nama Pelanggan')->searchable()->limit(25),
                Tables\Columns\TextColumn::make('sekolah')->label('Sekolah')->searchable()->limit(20),
                Tables\Columns\TextColumn::make('kodbuk')->label('Kode Buku')->searchable(),
                Tables\Columns\TextColumn::make('judbuk')->label('Judul Buku')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('total')->label('Total Bruto')->money('IDR', true)->sortable(),
            ])
            ->paginated([10, 25, 50, 100]);
    }
}