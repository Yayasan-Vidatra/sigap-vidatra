<x-mail::message>
# Pengaduan Anda sudah diterima

Yth. {{ $nama ?? 'Pelapor' }},

terima kasih telah menyampaikan pengaduan melalui SIGAP Vidatra.

<x-mail::panel>
**Kode lacak: {{ $kodeLacak }}**
</x-mail::panel>

- **Judul:** {{ $judul }}
@if ($unit)
- **Unit:** {{ $unit }}
@endif
@if ($diterimaPada)
- **Diterima:** {{ $diterimaPada }}
@endif

Simpan kode lacak ini untuk memantau status pengaduan. Tanggapan awal paling lambat 2 hari kerja, dan pengaduan diselesaikan paling lambat 14 hari kerja.

<x-mail::button :url="$tautanLacak">
Lacak Laporan
</x-mail::button>

Surel ini dikirim otomatis. Mohon tidak membalas surel ini; gunakan kanal pesan di halaman Lacak Laporan.

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
