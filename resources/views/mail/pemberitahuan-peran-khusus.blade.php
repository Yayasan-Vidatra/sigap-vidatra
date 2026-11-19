{{-- Tanpa isi laporan apa pun (Rancangan r1 Bagian 9). --}}
<x-mail::message>
# Perubahan peran akun

{{ $kalimat }}

Dasar: {{ $dasar }}.

Bila Anda tidak mengetahui perubahan ini, segera hubungi Ketua Dewan Pengawas.

<x-mail::button :url="$tautan">
Buka panel
</x-mail::button>

Surel ini dikirim otomatis; mohon tidak membalasnya.

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
