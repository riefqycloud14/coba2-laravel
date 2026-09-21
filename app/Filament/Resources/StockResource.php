<?php

namespace App\Filament\Resources;

use App\Exports\StockExport;
use App\Filament\Resources\StockResource\Pages;
use App\Models\Stock;
use App\Services\AxaptaSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class StockResource extends Resource
{
    protected static ?string $model = Stock::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Informasi Stok';
    protected static ?string $pluralModelLabel = 'Data Stok Realtime';

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
                        '81' => 'danger',
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
                        '81' => '81 - Banjarmasin',
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

    Action::make('export_excel')
        ->label('Download Excel Stok')
        ->icon('heroicon-o-document-arrow-down')
        ->color('success')
        ->action(function () {
            $filename = 'Laporan_Stok_Realtime_' . date('Y-m-d_H-i-s') . '.xlsx';
            return Excel::download(new StockExport(), $filename);
        }),
])            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStocks::route('/'),
        ];
    }
}