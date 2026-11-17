# Dokumen Acuan SIGAP Vidatra

Folder ini berisi tiga dokumen yang menjadi acuan pembangunan SIGAP Vidatra. Kode harus mengikuti dokumen ini; bila kode dan dokumen berbeda, dokumen yang benar sampai ada keputusan lain yang dicatat di [log deviasi](log-deviasi.md).

| File | Isi | Dipakai untuk |
| --- | --- | --- |
| [01-rancangan-sistem-r1.md](01-rancangan-sistem-r1.md) | Rancangan Sistem r1: aktor, matriks penerima, use case (UC-xx), status laporan, kebutuhan (F-xx), lampiran, keamanan, rancangan data | Aturan bisnis, hak akses, struktur data, keamanan |
| [02-tor.md](02-tor.md) | TOR Pengembangan: ruang lingkup Tahap 1/2, halaman dan fiturnya (H-xx), infrastruktur, timeline 15 hari, kriteria penerimaan | Daftar halaman, fitur per halaman, jadwal |
| [03-prd.md](03-prd.md) | PRD: user story (US-xx) dengan kriteria penerimaan, kebutuhan non-fungsional (NF-xx), rilis, matriks keterlacakan | Definisi selesai setiap pekerjaan |

## Urutan prioritas bila ada perbedaan

1. Peraturan Yayasan tentang Sistem Pengaduan dan Pelaporan Pelanggaran (di luar repo).
2. Rancangan Sistem r1 (`01`): aturan bisnis, hak akses, data, keamanan.
3. PRD (`03`): kriteria penerimaan.
4. TOR (`02`): halaman, cakupan tahap, jadwal.

## Kode yang dipakai

- **UC-xx**: use case di Rancangan Bagian 4–5.
- **F-xx**: kebutuhan fungsional di Rancangan Bagian 7.
- **H-xx**: halaman di TOR Bagian 3–5.
- **US-xx**: user story di PRD Bagian 4. Status progres tiap US dicatat di matriks keterlacakan PRD Bagian 7.
- **NF-xx**: kebutuhan non-fungsional di PRD Bagian 5.

## Catatan versi

Salinan di folder ini diekspor dari dokumen online pada 16–17 November 2026. Dokumen online adalah versi kerja: matriks keterlacakan dan log deviasi resmi diperbarui di PRD online. Bila dokumen online berubah, ekspor ulang dan ganti file di sini dalam satu commit `docs: perbarui dokumen acuan`.
