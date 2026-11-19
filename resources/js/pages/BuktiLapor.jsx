import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import PublicLayout from '../layouts/PublicLayout';

export default function BuktiLapor({ kodeLacak, judul, lampiran = [], masaUnduhPdfMenit }) {
    const [tersalin, setTersalin] = useState(false);

    async function salin() {
        try {
            await navigator.clipboard.writeText(kodeLacak);
            setTersalin(true);
        } catch {
            setTersalin(false);
        }
    }

    return (
        <PublicLayout lebar="sedang">
            <Head title="Bukti Lapor" />
            <section className="flex flex-col gap-4 rounded-2xl border border-pengaduan-200 bg-white p-5 sm:p-7">
                <h1 className="text-[28px] leading-tight font-extrabold tracking-tight sm:text-[34px]">Pengaduan Anda sudah diterima</h1>
                {judul && <p className="text-slate-600">“{judul}”</p>}
                <div className="flex flex-col gap-2 rounded-lg bg-pengaduan-50 p-4">
                    <span className="text-sm text-pengaduan-900">Kode lacak</span>
                    <span className="font-mono text-[28px] leading-tight font-extrabold tracking-tight sm:text-[34px] tracking-wider text-pengaduan-900 sm:text-3xl">
                        {kodeLacak}
                    </span>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={salin}
                            className="inline-flex min-h-11 items-center justify-center rounded-xl border-[1.5px] border-pengaduan-700 px-4 py-2.5 text-sm font-semibold text-pengaduan-800 hover:bg-pengaduan-100"
                        >
                            {tersalin ? 'Kode tersalin' : 'Salin kode'}
                        </button>
                        {/* Unduhan biasa (bukan kunjungan Inertia); kode lacak diambil server dari sesi, bukan dari URL. */}
                        <a
                            href="/selesai/pdf"
                            className="inline-flex min-h-11 items-center justify-center rounded-xl bg-pengaduan-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-pengaduan-800"
                        >
                            Unduh PDF bukti lapor
                        </a>
                    </div>
                    <p className="text-xs text-pengaduan-900">
                        PDF bisa diunduh selama {masaUnduhPdfMenit} menit setelah laporan dikirim.
                    </p>
                </div>
                {lampiran.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <h2 className="font-semibold">Lampiran terkirim</h2>
                        <ul className="flex list-disc flex-col gap-1 pl-5 text-sm text-slate-700">
                            {lampiran.map((nama) => (
                                <li key={nama}>{nama}</li>
                            ))}
                        </ul>
                        <p className="text-sm text-slate-600">
                            Lampiran sedang diperiksa keamanannya. Bila ada berkas yang tidak dapat diterima, Anda akan
                            diberi tahu di halaman Lacak Laporan.
                        </p>
                    </div>
                )}
                <p className="text-sm text-slate-700">
                    Simpan kode ini. Kode lacak dipakai untuk memantau status pengaduan di halaman{' '}
                    <Link href="/lacak" className="font-medium text-pengaduan-700 hover:underline">
                        Lacak Laporan
                    </Link>
                    . Halaman ini hanya tampil sekali.
                </p>
                <Link href="/" className="inline-flex min-h-11 items-center self-start font-semibold text-pengaduan-700 underline underline-offset-2">
                    Kembali ke Beranda
                </Link>
            </section>
        </PublicLayout>
    );
}
