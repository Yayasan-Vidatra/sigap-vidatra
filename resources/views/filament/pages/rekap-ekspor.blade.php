<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if ($rekap === null)
        <x-filament::section>
            <p class="text-sm text-gray-700 dark:text-gray-200">Periode tidak sah. Periksa tanggal awal dan akhir.</p>
        </x-filament::section>
    @else
        <x-filament::section heading="Pengaduan" description="Periode {{ $rekap['periode'] }}. {{ $rekap['unit'] }}; {{ strtolower($rekap['kategori']) }}. Rata-rata dihitung dalam hari kalender sejak masuk sampai Selesai. {{ \App\Services\Rekap\RekapLaporan::CATATAN_PINDAH_WBS }}">
            <div class="sigap-gulir-tabel">
                <table class="sigap-tabel-rekap">
                    <colgroup>
                        <col style="width: 16%">
                        <col>
                        <col style="width: 11%">
                        <col style="width: 11%">
                        <col style="width: 16%">
                        <col style="width: 14%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Unit</th>
                            <th scope="col">Kategori</th>
                            <th scope="col" class="sigap-angka">Jumlah</th>
                            <th scope="col" class="sigap-angka">Selesai</th>
                            <th scope="col" class="sigap-angka">Rata-rata hari penyelesaian</th>
                            <th scope="col" class="sigap-angka">Lewat batas waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rekap['pengaduan'] as $baris)
                            <tr>
                                <td>{{ $baris['unit'] }}</td>
                                <td>{{ $baris['kategori'] }}</td>
                                <td class="sigap-angka">{{ $baris['jumlah'] }}</td>
                                <td class="sigap-angka">{{ $baris['selesai'] }}</td>
                                <td class="sigap-angka">{{ \App\Services\Rekap\RekapLaporan::angka($baris['rata_rata_hari']) }}</td>
                                <td class="sigap-angka">{{ $baris['lewat_sla'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="sigap-kosong">Tidak ada pengaduan pada periode ini.</td></tr>
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
