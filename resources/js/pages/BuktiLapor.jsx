import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import PublicLayout from '../layouts/PublicLayout';

export default function BuktiLapor({ kodeLacak, judul, lampiran = [] }) {
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
        <PublicLayout>
            <Head title="Bukti Lapor" />
            <section className="flex flex-col gap-4 rounded-xl border border-teal-200 bg-white p-5">
                <h1 className="text-2xl font-bold">Pengaduan Anda sudah diterima</h1>
                {judul && <p className="text-slate-600">“{judul}”</p>}
                <div className="flex flex-col gap-2 rounded-lg bg-teal-50 p-4">
                    <span className="text-sm text-teal-900">Kode lacak</span>
                    <span className="font-mono text-2xl font-bold tracking-wider text-teal-900 sm:text-3xl">
                        {kodeLacak}
                    </span>
                    <button
                        type="button"
                        onClick={salin}
                        className="self-start rounded-lg border border-teal-700 px-3 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-100"
                    >
                        {tersalin ? 'Kode tersalin' : 'Salin kode'}
                    </button>
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
                    <Link href="/lacak" className="font-medium text-teal-700 hover:underline">
                        Lacak Laporan
                    </Link>
                    . Halaman ini hanya tampil sekali.
                </p>
                <Link href="/" className="font-medium text-teal-700 hover:underline">
                    Kembali ke Beranda
                </Link>
            </section>
        </PublicLayout>
    );
}
