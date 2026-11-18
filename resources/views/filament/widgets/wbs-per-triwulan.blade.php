<x-filament-widgets::widget>
    <x-filament::section heading="Laporan WBS per kategori per triwulan {{ $tahun }}" description="Hanya jumlah. Angka di bawah {{ $minimal }} tidak ditampilkan agar pelapor dan terlapor tidak dapat dikenali.">
        @include('filament.rekap.tabel-wbs', ['baris' => $baris, 'minimal' => $minimal])
    </x-filament::section>
</x-filament-widgets::widget>
