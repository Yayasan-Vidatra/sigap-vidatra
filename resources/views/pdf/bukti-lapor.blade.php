{{-- Bukti lapor (H-05, US-07). Tanpa gambar, CSS, atau font dari luar; dompdf enable_remote = false. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Lapor SIGAP Vidatra</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #0f172a; }
        h1 { font-size: 16pt; margin: 0 0 4pt; }
        .sub { color: #475569; margin: 0 0 18pt; }
        .kode { border: 1pt solid #0f766e; padding: 12pt; margin: 0 0 16pt; }
        .label { font-size: 9pt; color: #475569; }
        .nilai-kode { font-size: 20pt; font-weight: bold; letter-spacing: 1pt; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 16pt; }
        td { padding: 4pt 0; vertical-align: top; }
        td.kiri { width: 32%; color: #475569; }
        h2 { font-size: 12pt; margin: 0 0 6pt; }
        ul { margin: 0; padding-left: 14pt; }
        li { margin: 0 0 4pt; }
        .kaki { margin-top: 24pt; font-size: 9pt; color: #64748b; }
    </style>
</head>
<body>
    <h1>Bukti Lapor</h1>
    <p class="sub">SIGAP Vidatra, Yayasan Vidatra</p>

    <div class="kode">
        <div class="label">Kode lacak</div>
        <div class="nilai-kode">{{ $kodeLacak }}</div>
    </div>

    <table>
        <tr>
            <td class="kiri">Kanal</td>
            <td>{{ $wbs ? 'Pelaporan Pelanggaran (WBS)' : 'Pengaduan' }}</td>
        </tr>
        <tr>
            <td class="kiri">Dikirim</td>
            <td>{{ $dikirimPada }}</td>
        </tr>
        <tr>
            <td class="kiri">Lacak laporan</td>
            <td>{{ $tautanLacak }}</td>
        </tr>
    </table>

    <h2>Petunjuk</h2>
    <ul>
        <li>Simpan dokumen ini atau catat kode lacak. Kode lacak tidak dapat dipulihkan bila hilang.</li>
        <li>Buka alamat Lacak Laporan di atas dan masukkan kode lacak untuk melihat status serta membalas pesan petugas.</li>
        @if ($wbs)
            <li>Laporan WBS juga memerlukan PIN yang Anda buat saat melapor. PIN tidak tercantum di dokumen ini.</li>
        @else
            <li>Tanggapan awal paling lambat 2 hari kerja, dan pengaduan diselesaikan paling lambat 14 hari kerja.</li>
        @endif
        <li>Jangan bagikan kode lacak kepada orang lain.</li>
    </ul>

    <p class="kaki">Dokumen ini dibuat otomatis dan tidak memuat identitas pelapor maupun isi laporan.</p>
</body>
</html>
