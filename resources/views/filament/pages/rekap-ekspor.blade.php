<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if ($rekap === null)
        <x-filament::section>
            <p class="text-sm text-danger-600">Periode tidak sah. Periksa tanggal awal dan akhir.</p>
        </x-filament::section>
    @else
        <x-filament::section heading="Pengaduan" description="Periode {{ $rekap['periode'] }}. {{ $rekap['unit'] }}; {{ strtolower($rekap['kategori']) }}. Rata-rata dihitung dalam hari kalender sejak masuk sampai Selesai.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-white/10">
                            <th class="py-2 pe-4 font-medium">Unit</th>
                            <th class="py-2 pe-4 font-medium">Kategori</th>
                            <th class="py-2 pe-4 text-end font-medium">Jumlah</th>
                            <th class="py-2 pe-4 text-end font-medium">Selesai</th>
                            <th class="py-2 pe-4 text-end font-medium">Rata-rata hari penyelesaian</th>
                            <th class="py-2 pe-4 text-end font-medium">Lewat batas waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekap['pengaduan'] as $baris)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pe-4">{{ $baris['unit'] }}</td>
                                <td class="py-2 pe-4">{{ $baris['kategori'] }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $baris['jumlah'] }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $baris['selesai'] }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $baris['rata_rata_hari'] ?? '-' }}</td>
                                <td class="py-2 pe-4 text-end tabular-nums">{{ $baris['lewat_sla'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-2 text-gray-500">Tidak ada pengaduan pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @if ($rekap['wbs'] !== null)
            <x-filament::section heading="Laporan WBS per kategori per triwulan {{ $rekap['tahun_wbs'] }}" description="Hanya jumlah. Angka di bawah {{ $minimal }} tidak ditampilkan agar pelapor dan terlapor tidak dapat dikenali.">
                @include('filament.rekap.tabel-wbs', ['baris' => $rekap['wbs'], 'minimal' => $minimal])
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
