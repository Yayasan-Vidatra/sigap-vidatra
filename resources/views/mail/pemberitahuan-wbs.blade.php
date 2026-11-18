{{-- Tanpa kode lacak, isi laporan, nama terlapor, atau identitas pelapor (aturan proyek no. 4 dan 5). --}}
<x-mail::message>
# Ruang Kerja WBS

{{ $kalimat }}

Isi laporan hanya dapat dibaca di panel setelah login dengan 2FA.

<x-mail::button :url="$tautan">
Buka di panel
</x-mail::button>

Jangan meneruskan surel ini. Surel ini dikirim otomatis; mohon tidak membalasnya.

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
