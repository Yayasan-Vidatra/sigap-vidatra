import { Head, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import Altcha from '../../components/Altcha';
import PublicLayout, { Kolom, Tombol, gayaIsian } from '../../layouts/PublicLayout';
import Formulir from './Formulir';
import Jaminan from './Jaminan';

/*
 * Pintu kanal WBS internal (/wbs; revisi 20-11, log deviasi). Satu halaman dengan tiga langkah yang hanya ada di
 * memori halaman: kode akses (wajib ALTCHA), jaminan kerahasiaan (H-03), lalu Formulir WBS (H-04). Kode yang benar
 * menghasilkan token akses bertanda tangan; token itu yang membuka data formulir. Tidak ada token atau kode di URL,
 * sesi, cookie, atau riwayat browser, sehingga memuat ulang halaman kembali ke langkah kode akses.
 */
export default function Akses({ dibuka, petunjuk, pesanTutup }) {
    const form = useHttp({ kode: '', altcha: '' });
    const buka = useHttp({ token_akses: '' });
    const [kunciAltcha, setKunciAltcha] = useState(0);
    const [galatUmum, setGalatUmum] = useState(null);
    const [akses, setAkses] = useState(null);
    const [formulir, setFormulir] = useState(null);

    async function periksa(e) {
        e.preventDefault();
        setGalatUmum(null);

        try {
            const hasil = await form.post('/wbs/akses', {
                onHttpException: (jawaban) => {
                    setGalatUmum(
                        jawaban.status === 429 ? 'Terlalu banyak percobaan dalam waktu singkat. Silakan coba lagi dalam satu menit.' : pesanTutup,
                    );
                },
                onNetworkError: () => setGalatUmum('Koneksi terputus. Silakan coba lagi.'),
            });

            if (hasil) {
                setAkses(hasil);
                window.scrollTo(0, 0);

                return;
            }
        } catch {
            // Galat umum sudah ditampilkan.
        }

        // Solusi ALTCHA hanya sekali pakai; minta verifikasi baru untuk percobaan berikutnya.
        form.setData('altcha', '');
        setKunciAltcha((kunci) => kunci + 1);
    }

    async function lanjut() {
        setGalatUmum(null);
        buka.transform(() => ({ token_akses: akses.tokenAkses }));

        try {
            const hasil = await buka.post('/wbs/lapor/buka', {
                onHttpException: () => {
                    setAkses(null);
                    setGalatUmum('Masa akses sudah habis. Masukkan kode akses lagi.');
                },
                onNetworkError: () => setGalatUmum('Koneksi terputus. Silakan coba lagi.'),
            });

            if (hasil) {
                setFormulir(hasil);
                window.scrollTo(0, 0);
            }
        } catch {
            // Galat sudah ditampilkan.
        }
    }

    if (formulir) {
        return <Formulir {...formulir} />;
    }

    if (akses) {
        return <Jaminan {...akses.jaminan} onLanjut={lanjut} memuat={buka.processing} galat={galatUmum} />;
    }

    return (
        <PublicLayout lebar="sempit" wbs>
            <Head title="Pelaporan Pelanggaran" />

            <div className="flex flex-col gap-2">
                <h1 className="text-[28px] leading-tight font-extrabold tracking-tight sm:text-[34px]">Pelaporan Pelanggaran (WBS)</h1>
                <p className="text-slate-600">Kanal ini untuk kalangan internal Yayasan Vidatra. Masukkan kode akses untuk melanjutkan.</p>
            </div>

            {!dibuka ? (
                <p role="status" className="mt-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 font-semibold text-amber-900">
                    {pesanTutup}
                </p>
            ) : (
                <form onSubmit={periksa} noValidate autoComplete="off" className="mt-6 flex flex-col gap-5 rounded-2xl border border-garis bg-white p-5 sm:p-7">
                    {galatUmum && (
                        <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800">
                            {galatUmum}
                        </p>
                    )}
                    <Kolom id="kode" label="Kode akses" wajib galat={form.errors.kode}>
                        <input
                            id="kode"
                            name="kode"
                            type="password"
                            autoComplete="off"
                            spellCheck={false}
                            value={form.data.kode}
                            onChange={(e) => form.setData('kode', e.target.value)}
                            aria-invalid={form.errors.kode ? true : undefined}
                            aria-describedby={[petunjuk ? 'kode-keterangan' : null, form.errors.kode ? 'kode-galat' : null].filter(Boolean).join(' ') || undefined}
                            className={gayaIsian}
                        />
                        {petunjuk && (
                            <p id="kode-keterangan" className="text-sm text-slate-600">
                                {petunjuk}
                            </p>
                        )}
                    </Kolom>
                    <Altcha key={kunciAltcha} onChange={(payload) => form.setData('altcha', payload)} galat={form.errors.altcha} />
                    <Tombol type="submit" varian="wbs" disabled={form.processing} className="max-sm:w-full">
                        {form.processing ? 'Memeriksa…' : 'Lanjut'}
                    </Tombol>
                </form>
            )}
        </PublicLayout>
    );
}
