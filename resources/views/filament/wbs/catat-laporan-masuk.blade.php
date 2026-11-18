{{--
    H-14 Catat Laporan Masuk. Setelah tersimpan, kode lacak dan PIN sementara tampil sekali di sini dari state
    komponen, tidak lewat notifikasi sesi, agar PIN tidak tersimpan di tabel sessions.
--}}
<x-filament-panels::page>
    @if ($hasil)
        <x-filament::section>
            <x-slot name="heading">Laporan tercatat</x-slot>
            <x-slot name="description">Sampaikan kode lacak dan PIN sementara kepada pelapor lewat saluran asal. Keduanya tidak ditampilkan lagi setelah halaman ini ditinggalkan.</x-slot>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Kode lacak</dt>
                    <dd class="font-mono text-2xl font-bold tracking-wider" id="hasil-kode-lacak">{{ $hasil['kode_lacak'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">PIN sementara</dt>
                    <dd class="font-mono text-2xl font-bold tracking-wider" id="hasil-pin">{{ $hasil['pin'] }}</dd>
                </div>
            </dl>

            @if ($hasil['tertutup'])
                <p class="mt-4 text-sm text-warning-600 dark:text-warning-400">
                    Menurut matriks penerima, laporan ini ditangani {{ $hasil['penelaah'] }}, sehingga tidak terbuka di Ruang Kerja Anda.
                </p>
            @endif

            <div class="mt-6 flex flex-wrap gap-3">
                <x-filament::button tag="a" :href="\App\Filament\Resources\LaporanWbs\LaporanWbsResource::getUrl('catat')">
                    Catat laporan lain
                </x-filament::button>
                @unless ($hasil['tertutup'])
                    <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\LaporanWbs\LaporanWbsResource::getUrl('view', ['record' => $hasil['id']])">
                        Buka laporan
                    </x-filament::button>
                @endunless
            </div>
        </x-filament::section>
    @else
        {{ $this->content }}
    @endif
</x-filament-panels::page>
