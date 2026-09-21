<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Invoices';

    // Mematikan tombol 'Create' karena data ditarik otomatis dari Axapta
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')->label('No. Faktur')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tglfak')->label('Tgl Faktur')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('bu')->label('BU')->sortable(),
                Tables\Columns\TextColumn::make('accountnum')->label('Kode Lan')->searchable(),
                Tables\Columns\TextColumn::make('namlan')->label('Nama Pelanggan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sekolah')->label('Sekolah')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('Tipe')->badge(),
                Tables\Columns\TextColumn::make('netto')->label('Netto')->money('IDR')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bu')
                    ->label('Filter Cabang (BU)')
                    ->options([
                        '61' => 'Pontianak (61)',
                        '59' => 'Samarinda (59)',
                        '81' => 'Banjarmasin (81)',
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view'  => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}