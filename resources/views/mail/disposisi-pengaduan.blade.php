<x-mail::message>
# Pengaduan baru untuk unit Anda

Sebuah pengaduan didisposisikan kepada Anda untuk ditindaklanjuti.

<x-mail::panel>
**Kode lacak: {{ $kodeLacak }}**<br>
Judul: {{ $judul }}<br>
@if ($kategori)
Kategori: {{ $kategori }}<br>
@endif
@if ($prioritas)
Prioritas: {{ $prioritas }}<br>
@endif
@if ($batasWaktu)
Batas waktu selesai: {{ $batasWaktu }}
@endif
</x-mail::panel>

@if ($instruksi)
**Instruksi Admin:** {{ $instruksi }}
@endif

Rincian pengaduan dan lampiran hanya dapat dibuka di panel setelah login.

<x-mail::button :url="$tautan">
Buka di panel
</x-mail::button>

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
