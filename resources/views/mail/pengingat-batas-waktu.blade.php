<x-mail::message>
@if ($eskalasi)
# Pengaduan melewati batas waktu

Pengaduan berikut belum selesai ditindaklanjuti unit sampai batas waktunya.
@else
# Batas waktu tinggal 2 hari kerja

Pengaduan berikut perlu diselesaikan unit Anda sebelum batas waktunya.
@endif

<x-mail::panel>
**Kode lacak: {{ $kodeLacak }}**<br>
@if ($unit)
Unit: {{ $unit }}<br>
@endif
@if ($kategori)
Kategori: {{ $kategori }}<br>
@endif
@if ($pic)
PIC: {{ $pic }}<br>
@endif
Batas waktu: {{ $batasWaktu }}
</x-mail::panel>

@if ($tautan)
<x-mail::button :url="$tautan">
Buka di panel
</x-mail::button>
@endif

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
