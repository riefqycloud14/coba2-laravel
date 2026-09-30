<?php

namespace App\Filament\Resources;

use App\Exports\StockExport;
use App\Filament\Resources\StockResource\Pages;
use App\Models\Stock;
use App\Services\AxaptaSyncService;
use App\Services\StockAnalysisService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockResource extends Resource
{
    protected static ?string $model = Stock::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Informasi Stok & SO';
    protected static ?string $pluralModelLabel = 'Data Stok Realtime & SO Outstanding';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bu')
                    ->label('BU / Cabang')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '61' => 'success',
                        '59' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('inventlocationid')
                    ->label('Gudang (WH)')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('itemid')
                    ->label('Kode Buku / Item')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('stok_physical')
                    ->label('Stok Fisik')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stok_transit')
                    ->label('Stok Transit')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('po_outstanding')
                    ->label('PO Outstanding')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Sync')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bu')
                    ->label('Filter Cabang')
                    ->options([
                        '61' => '61 - Pontianak',
                        '59' => '59 - Samarinda',
                    ]),
            ])
            ->headerActions([
                Action::make('sync_stocks_and_so')
                    ->label('Proses Tarik Stok & SO Realtime')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        try {
                            // Menjalankan penarikan Stok + SO Outstanding sekaligus
                            (new AxaptaSyncService())->syncStocksAndSalesOrders();

                            Notification::make()
                                ->title('Sinkronisasi Berhasil')
                                ->body('Data Stok Realtime dan SO Outstanding berhasil diperbarui dari Axapta.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal Sinkronisasi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 1. Download Excel Stok dengan Pilihan Modal Cabang
                Action::make('export_excel')
                    ->label('Download Excel Stok')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('bu')
                            ->label('Pilih Cabang / BU')
                            ->options([
                                'all' => 'Semua Cabang (59 & 61)',
                                '59'  => '59 - Samarinda',
                                '61'  => '61 - Pontianak',
                            ])
                            ->default('all')
                            ->required(),
                    ])
                    ->modalHeading('Download Laporan Stok')
                    ->modalSubmitActionLabel('Download')
                    ->action(function (array $data): StreamedResponse {
                        $buChoice = $data['bu'];
                        $filename = 'Laporan_Stok_Realtime_' . ($buChoice === 'all' ? 'Semua' : 'BU_' . $buChoice) . '_' . date('Y-m-d_H-i-s') . '.csv';

                        return response()->streamDownload(function () use ($buChoice) {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF");

                            fputcsv($handle, [
                                'BU / Cabang', 'Gudang (WH)', 'Kode Buku / Item', 
                                'Stok Fisik', 'Stok Transit', 'PO Outstanding', 'Terakhir Sync'
                            ]);

                            $query = DB::table('stocks');
                            if ($buChoice !== 'all') {
                                $query->where('bu', $buChoice);
                            }

                            $query->orderBy('bu', 'asc')
                                ->orderBy('itemid', 'asc')
                                ->chunk(1000, function ($rows) use ($handle) {
                                    foreach ($rows as $r) {
                                        fputcsv($handle, [
                                            $r->bu ?? '',
                                            $r->inventlocationid ?? '',
                                            '="' . ($r->itemid ?? '') . '"',
                                            $r->stok_physical ?? 0,
                                            $r->stok_transit ?? 0,
                                            $r->po_outstanding ?? 0,
                                            $r->updated_at ?? '',
                                        ]);
                                    }
                                });

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),

                // 2. Download Excel SO dengan Pilihan Modal Cabang
                Action::make('export_so_excel')
                    ->label('Download Excel SO')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('bu')
                            ->label('Pilih Cabang / BU')
                            ->options([
                                'all' => 'Semua Cabang (59 & 61)',
                                '59'  => '59 - Samarinda',
                                '61'  => '61 - Pontianak',
                            ])
                            ->default('all')
                            ->required(),
                    ])
                    ->modalHeading('Download Laporan SO Outstanding')
                    ->modalSubmitActionLabel('Download')
                    ->action(function (array $data): StreamedResponse {
                        $buChoice = $data['bu'];
                        $filename = 'Laporan_SO_Outstanding_' . ($buChoice === 'all' ? 'Semua' : 'BU_' . $buChoice) . '_' . date('Y-m-d_H-i-s') . '.csv';

                        return response()->streamDownload(function () use ($buChoice) {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF");

                            fputcsv($handle, [
                                'BU', 'SO Eks', 'SO Amount', 'SO Nett', 'SWA', 'Jenis SO', 'Sales ID', 
                                'Sales Responsible', 'Sales Status', 'Sales Unit ID', 'Agr Sales Resp Name', 
                                'Created Date Time', 'Dimension', 'Dimension 2', 'Dimension 3', 'TCN SO Type', 
                                'Approval Status', 'Customer Ref', 'Purch Order Form Num', 'DataAreaID', 'RecID', 
                                'DataAreaID 2', 'Item ID', 'Qty Ordered', 'Sales Price', 'Line Disc', 
                                'Sales Group', 'Line Amount', 'Invent Dim ID', 'Line Percent', 'DataAreaID 3', 
                                'Account Num', 'Agr School ID', 'DataAreaID 4', 'Item Name', 'Agr Pengarang', 
                                'Agr Brand Name', 'Agr Grade Name', 'Agr Curriculum', 'Agr Segment', 'Status Issue'
                            ]);

                            $query = DB::table('sales_order_outstandings');
                            if ($buChoice !== 'all') {
                                $query->where('bu', $buChoice);
                            }

                            $query->orderBy('id', 'asc')
                                ->chunk(1000, function ($rows) use ($handle) {
                                    foreach ($rows as $r) {
                                        fputcsv($handle, [
                                            $r->bu ?? '',
                                            $r->so_eks ?? 0,
                                            number_format($r->so_amount ?? 0, 0, '', ''),
                                            number_format($r->so_nett ?? 0, 0, '', ''),
                                            $r->swa ?? '',
                                            $r->jenis_so ?? '',
                                            '="' . ($r->sales_id ?? '') . '"',
                                            $r->sales_responsible ?? '',
                                            $r->sales_status ?? '',
                                            $r->sales_unit_id ?? '',
                                            $r->agr_sales_resp_name ?? '',
                                            $r->created_date_time1 ?? '',
                                            $r->dimension ?? '',
                                            $r->dimension2_ ?? '',
                                            $r->dimension3_ ?? '',
                                            $r->tcn_sotype ?? '',
                                            $r->approval_status ?? '',
                                            $r->customer_ref ?? '',
                                            $r->purch_order_form_num ?? '',
                                            $r->dataareaid ?? '',
                                            $r->recid ?? '',
                                            $r->dataareaid_2 ?? '',
                                            '="' . ($r->item_id ?? '') . '"',
                                            $r->qty_ordered ?? 0,
                                            number_format($r->sales_price ?? 0, 0, '', ''),
                                            number_format($r->line_disc ?? 0, 0, '', ''),
                                            $r->sales_group ?? '',
                                            number_format($r->line_amount ?? 0, 0, '', ''),
                                            $r->invent_dim_id ?? '',
                                            $r->line_percent ?? 0,
                                            $r->dataareaid_3 ?? '',
                                            '="' . ($r->account_num ?? '') . '"',
                                            $r->agr_school_id ?? '',
                                            $r->dataareaid_4 ?? '',
                                            $r->item_name ?? '',
                                            $r->agr_pengarang ?? '',
                                            $r->agr_brand_name ?? '',
                                            $r->agr_grade_name ?? '',
                                            $r->agr_item_curriculum ?? '',
                                            $r->agr_item_segment ?? '',
                                            $r->status_issue ?? '',
                                        ]);
                                    }
                                });

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),

                // 3. Tombol Eksekusi Analisa Stok & Barter ala FoxPro
                Action::make('analisa_stok')
                    ->label('Analisa Stok & Barter')
                    ->icon('heroicon-o-calculator')
                    ->color('danger')
                    ->action(function () {
                        return (new StockAnalysisService())->exportCsv();
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStocks::route('/'),
        ];
    }
}