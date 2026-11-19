{{-- Tanpa isi laporan apa pun (Rancangan r1 Bagian 9). --}}
<x-mail::message>
# Buat kata sandi

Akun SIGAP Vidatra dengan peran {{ $peran }} sudah disiapkan untuk Anda. Buat kata sandi lewat tombol di bawah, lalu pasang 2FA saat login pertama.

<x-mail::button :url="$tautan">
Buat kata sandi
</x-mail::button>

Tautan ini hanya bisa dipakai sekali dan berlaku {{ $berlakuMenit }} menit. Jangan meneruskan surel ini. Bila tautan kedaluwarsa, minta dikirim ulang kepada pengelola sistem.

Salam,<br>
SIGAP Vidatra, Yayasan Vidatra
</x-mail::message>
