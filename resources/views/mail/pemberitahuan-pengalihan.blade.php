{{-- Tanpa kode lacak, ringkasan, nama terlapor, atau identitas pelapor. --}}
<x-mail::message>
# Pengalihan laporan

@if ($rujukanPokja)
Sebuah laporan kekerasan yang melibatkan Kepala Sekolah dirujuk Tim WBS ke Pokja BSAN. Pemberitahuan ini dikirim kepada Ketua Umum dan Dewan Pengawas sesuai ketentuan pengalihan.
@else
Ada laporan yang dialihkan kepada Anda. Buka panel untuk membaca ringkasannya, mengonfirmasi terima, dan mencatat tindak lanjut.

<x-mail::button :url="$tautan">
Buka Laporan Dialihkan
</x-mail::button>
@endif

Jangan meneruskan surel ini. Surel ini dikirim otomatis; mohon tidak membalasnya.

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
