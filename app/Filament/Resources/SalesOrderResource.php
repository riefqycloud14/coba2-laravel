<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesOrderResource\Pages;
use App\Models\SalesOrderOutstanding;
use App\Services\AxaptaSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrderOutstanding::class;
    // Sembunyikan dari sidebar navigasi kiri
    protected static bool $shouldRegisterNavigation = false;

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
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('sales_id')
                    ->label('Nomor SO')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('account_num')
                    ->label('Kode Customer')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('erl_invent_location_id')
                    ->label('Gudang (WH)')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('item_id')
                    ->label('Kode Buku / Item')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('item_name')
                    ->label('Nama Buku')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('so_eks')
                    ->label('SO Eks')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('so_nett')
                    ->label('SO Nett')
                    ->money('IDR', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_date_time1')
                    ->label('Tanggal SO')
                    ->dateTime('d M Y')
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
                Action::make('sync_sales_orders')
                    ->label('Proses Tarik SO Realtime')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        try {
                            (new AxaptaSyncService())->syncSalesOrderOutstandings();

                            Notification::make()
                                ->title('Sinkronisasi SO Berhasil')
                                ->body('Data SO Outstanding 41 kolom berhasil diperbarui dari Axapta.')
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
                    ->label('Download Excel SO (CSV/Excel)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (): StreamedResponse {
                        $filename = 'Laporan_SO_Outstanding_Lengkap_' . date('Y-m-d_H-i-s') . '.csv';

                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF"); // Format UTF-8 BOM untuk Excel

                            // Header CSV Lengkap 41 Kolom
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

                            DB::table('sales_order_outstandings')
                                ->orderBy('id', 'asc')
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