<x-mail::message>
# Status pengaduan Anda: {{ $status }}

Yth. Pelapor,

{{ $penjelasan }}

<x-mail::panel>
**Kode lacak: {{ $kodeLacak }}**<br>
Status: {{ $status }}<br>
Diperbarui: {{ $waktu }}
</x-mail::panel>

@if ($catatan)
**Keterangan:** {{ $catatan }}
@endif

<x-mail::button :url="$tautanLacak">
Lacak Laporan
</x-mail::button>

Surel ini dikirim otomatis. Mohon tidak membalas surel ini; gunakan kanal pesan di halaman Lacak Laporan.

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
