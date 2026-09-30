<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4 flex items-center justify-end gap-3">
            {{-- Tampilan Loading Spinner & Teks --}}
            <div wire:loading wire:target="submit" class="flex items-center gap-2 text-sm text-amber-600 font-medium">
                <svg class="animate-spin h-5 w-5 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Sedang menarik data dari Server AX, mohon tunggu...</span>
            </div>

            {{-- Tombol Submit --}}
            <x-filament::button 
                type="submit" 
                color="warning" 
                icon="heroicon-o-arrow-path"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                Proses Tarik Data Utama
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>