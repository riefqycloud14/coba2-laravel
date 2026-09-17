<x-filament-panels::page>
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button 
                type="submit" 
                icon="heroicon-m-arrow-down-tray" 
                size="lg"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="submit">
                    Proses Tarik Data Transaksi
                </span>
                <span wire:loading wire:target="submit">
                    Sedang Menarik & Memproses Data...
                </span>
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    {{-- STATISTIK RINGKASAN HASIL PENARIKAN DATA --}}
    @if($isProcessed)
        <div class="mt-8 space-y-6">
            <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                Hasil Penarikan Data Transaksi (Periode {{ $tglAwalShow }} s/d {{ $tglAkhirShow }})
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 font-medium uppercase">Total Transaksi</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($summaryStat['total_count'] ?? 0) }} Faktur
                    </p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 font-medium uppercase">Total Bruto</p>
                    <p class="text-2xl font-bold text-primary-600 mt-1">
                        Rp {{ number_format($summaryStat['total_bruto'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 font-medium uppercase">Total Netto</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">
                        Rp {{ number_format($summaryStat['total_netto'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
                <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700">
                    <p class="text-xs text-gray-500 font-medium uppercase">Rincian SWA / NSW</p>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-1">
                        SW: {{ number_format($summaryStat['total_sw'] ?? 0) }} | NSW: {{ number_format($summaryStat['total_nsw'] ?? 0) }}
                    </p>
                </div>
            </div>

            {{-- TABEL HASIL DATA FAKTUR --}}
            <div class="mt-4">
                {{ $this->table }}
            </div>
        </div>
    @endif
</x-filament-panels::page>