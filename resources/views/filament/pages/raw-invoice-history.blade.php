<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-m-arrow-path">
                Proses Tarik Data Utama
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>