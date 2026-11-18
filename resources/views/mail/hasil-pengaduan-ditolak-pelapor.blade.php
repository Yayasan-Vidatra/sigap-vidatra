<x-mail::message>
# Pelapor menolak hasil tindak lanjut

Pengaduan **{{ $kodeLacak }}**@if ($unit) (unit {{ $unit }})@endif kembali berstatus Diproses karena pelapor menolak hasilnya.

@if ($alasan)
**Alasan pelapor:** {{ $alasan }}
@endif

Mohon tinjau dan koordinasikan tindak lanjut berikutnya dengan unit terkait.

<x-mail::button :url="$tautan">
Buka di panel
</x-mail::button>

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
