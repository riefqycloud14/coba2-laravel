<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Invoice;
use App\Models\Customer;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Invoice Ditarik', Invoice::count())
                ->description('Gabungan Pontianak & Samarinda')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Invoice Pontianak (BU 61)', Invoice::where('bu', '61')->count())
                ->description('Cabang Pontianak')
                ->color('success'),

            Stat::make('Invoice Samarinda (BU 59)', Invoice::where('bu', '59')->count())
                ->description('Cabang Samarinda')
                ->color('info'),

            Stat::make('Total Master Pelanggan', Customer::count())
                ->description('Pelanggan Terdaftar')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}