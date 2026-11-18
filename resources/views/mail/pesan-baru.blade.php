<x-mail::message>
# Ada pesan baru

Ada pesan baru untuk laporan **{{ $kodeLacak }}**.

@if ($untukPetugas)
Pesan dari pelapor dapat dibaca di panel setelah login.
@else
Buka halaman Lacak Laporan dan masukkan kode lacak Anda untuk membaca pesan dari petugas.
@endif

<x-mail::button :url="$tautan">
{{ $untukPetugas ? 'Buka di panel' : 'Lacak Laporan' }}
</x-mail::button>

Surel ini dikirim otomatis. Mohon tidak membalas surel ini.

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
