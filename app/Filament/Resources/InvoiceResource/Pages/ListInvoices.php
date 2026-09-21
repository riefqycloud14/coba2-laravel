<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAxapta')
                ->label('Sync Data Axapta')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sinkronisasi')
                ->modalDescription('Proses ini akan menarik Master Item, Master Customer, dan Transaksi Invoice terbaru dari Axapta. Lanjutkan?')
                ->modalSubmitActionLabel('Ya, Tarik Data')
                ->action(function () {
                    // Jalankan Artisan Command backend yang sudah kita uji
                    Artisan::call('axapta:sync');

                    Notification::make()
                        ->title('Sinkronisasi Berhasil!')
                        ->body('Data Master Item, Customer, dan Transaksi Invoice telah berhasil diperbarui.')
                        ->success()
                        ->send();
                }),
        ];
    }
}