{{-- Rekap (H-18, US-23). Hanya angka; tanpa isi laporan, identitas, atau terlapor. Tanpa aset dari luar. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap SIGAP Vidatra</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #0f172a; }
        h1 { font-size: 15pt; margin: 0 0 4pt; }
        h2 { font-size: 12pt; margin: 16pt 0 6pt; }
        .sub { color: #475569; margin: 0 0 4pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 0.5pt solid #cbd5e1; padding: 4pt; text-align: left; }
        th.angka, td.angka { text-align: right; }
        .kaki { margin-top: 18pt; font-size: 8pt; color: #64748b; }
    </style>
</head>
<body>
    <h1>Rekap SIGAP Vidatra</h1>
    <p class="sub">Periode {{ $periode }} · {{ $unit }} · {{ $kategori }}</p>

    <h2>Pengaduan</h2>
    <table>
        <thead>
            <tr>
                <th>Unit</th><th>Kategori</th><th class="angka">Jumlah</th><th class="angka">Selesai</th>
                <th class="angka">Rata-rata hari penyelesaian</th><th class="angka">Lewat batas waktu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pengaduan as $baris)
                <tr>
                    <td>{{ $baris['unit'] }}</td><td>{{ $baris['kategori'] }}</td>
                    <td class="angka">{{ $baris['jumlah'] }}</td><td class="angka">{{ $baris['selesai'] }}</td>
                    <td class="angka">{{ \App\Services\Rekap\RekapLaporan::angka($baris['rata_rata_hari']) }}</td><td class="angka">{{ $baris['lewat_sla'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Tidak ada pengaduan pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="sub">{{ \App\Services\Rekap\RekapLaporan::CATATAN_PINDAH_WBS }}</p>

    @if ($wbs !== null)
        <h2>Laporan WBS per kategori per triwulan {{ $tahun_wbs }}</h2>
        <p class="sub">Hanya jumlah. Angka di bawah {{ $minimal }} tidak ditampilkan.</p>
        <table>
            <thead>
                <tr><th>Kategori</th><th class="angka">Triwulan 1</th><th class="angka">Triwulan 2</th><th class="angka">Triwulan 3</th><th class="angka">Triwulan 4</th></tr>
            </thead>
            <tbody>
                @foreach ($wbs as $baris)
                    <tr>
                        <td>{{ $baris['kategori'] }}</td>
                        @foreach ($baris['triwulan'] as $nilai)
                            <td class="angka">{{ $nilai ?? '< '.$minimal }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="kaki">Dibuat {{ $dibuat }}. Rekap tidak memuat identitas pelapor, terlapor, atau isi laporan.</p>
</body>
</html>
