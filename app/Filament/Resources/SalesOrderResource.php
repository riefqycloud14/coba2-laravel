<?php

namespace App\Filament\Resources;

use App\Exports\SalesOrderExport;
use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\SalesOrder;
use App\Services\AxaptaSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'SO Outstanding';
    protected static ?string $pluralModelLabel = 'Data SO Outstanding';

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

                Tables\Columns\TextColumn::make('sales_id')
                    ->label('Nomor SO')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('accountnum')
                    ->label('Kode Customer')
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

                Tables\Columns\TextColumn::make('qty_order')
                    ->label('Qty Order')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('qty_outstanding')
                    ->label('Qty Outstanding')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_date')
                    ->label('Tanggal SO')
                    ->date('d M Y')
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
                Action::make('sync_sales_orders')
                    ->label('Proses Tarik SO Realtime')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        try {
                            (new AxaptaSyncService())->syncSalesOrders();

                            Notification::make()
                                ->title('Sinkronisasi SO Berhasil')
                                ->body('Data SO Outstanding berhasil diperbarui dari Axapta.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Gagal Sinkronisasi SO')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('export_excel')
                    ->label('Download Excel SO')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        $filename = 'Laporan_SO_Outstanding_' . date('Y-m-d_H-i-s') . '.xlsx';
                        return Excel::download(new SalesOrderExport(), $filename);
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesOrders::route('/'),
        ];
    }


}