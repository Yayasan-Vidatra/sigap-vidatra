import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Altcha from '../../components/Altcha';
import PublicLayout from '../../layouts/PublicLayout';

const gayaIsian =
    'w-full min-h-12 rounded-xl border-[1.5px] border-slate-300 bg-white px-3.5 py-3 text-base text-slate-900 focus:border-pengaduan-600 focus:outline-none focus:ring-2 focus:ring-pengaduan-600/30 aria-invalid:border-rose-500';

/*
 * H-06 Lacak Laporan untuk pengaduan. Kode tidak ada dan kode salah mendapat pesan yang sama dari server. Setelah 5
 * kali gagal, server meminta CAPTCHA dan jeda.
 */
export default function Masuk({ perluCaptcha, sisaJeda, pesan }) {
    const { data, setData, post, processing, errors } = useForm({ kode: '', altcha: '' });
    const [jeda, setJeda] = useState(sisaJeda);

    useEffect(() => setJeda(sisaJeda), [sisaJeda]);

    useEffect(() => {
        if (jeda <= 0) {
            return undefined;
        }

        const pewaktu = window.setTimeout(() => setJeda((detik) => Math.max(0, detik - 1)), 1000);

        return () => window.clearTimeout(pewaktu);
    }, [jeda]);

    function kirim(e) {
        e.preventDefault();
        post('/lacak', { preserveScroll: true });
    }

    return (
        <PublicLayout>
            <Head title="Lacak Laporan" />

            <div className="mx-auto flex max-w-lg flex-col gap-2">
                <h1 className="text-[28px] leading-tight font-extrabold tracking-tight sm:text-[34px]">Lacak Laporan</h1>
                <p className="text-slate-600">
                    Masukkan kode lacak dari bukti lapor untuk melihat status, riwayat, dan pesan dari petugas.
                </p>
            </div>

            <form onSubmit={kirim} noValidate className="mx-auto mt-6 flex max-w-lg flex-col gap-5 rounded-2xl border border-garis bg-white p-5 sm:p-7">
                {pesan && (
                    <p role="status" className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        {pesan}
                    </p>
                )}

                <div className="flex flex-col gap-1.5">
                    <label htmlFor="kode" className="font-medium text-slate-800">
                        Kode lacak
                    </label>
                    <input
                        id="kode"
                        name="kode"
                        type="text"
                        autoComplete="off"
                        autoCapitalize="characters"
                        spellCheck={false}
                        placeholder="PGD-2611-XXXXXX"
                        value={data.kode}
                        onChange={(e) => setData('kode', e.target.value)}
                        aria-invalid={errors.kode ? true : undefined}
                        aria-describedby={errors.kode ? 'kode-galat' : undefined}
                        className={`${gayaIsian} font-mono uppercase tracking-wider`}
                    />
                    {errors.kode && (
                        <p id="kode-galat" className="text-sm text-rose-700">
                            {errors.kode}
                        </p>
                    )}
                </div>

                {perluCaptcha && (
                    <div className="flex flex-col gap-2">
                        <p className="text-sm text-slate-700">Terlalu banyak percobaan. Selesaikan verifikasi berikut.</p>
                        <Altcha onChange={(payload) => setData('altcha', payload)} galat={errors.altcha} />
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing || jeda > 0}
                    className="inline-flex min-h-11 items-center justify-center rounded-xl bg-pengaduan-700 px-5 py-3 font-bold text-white hover:bg-pengaduan-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                >
                    {jeda > 0 ? `Coba lagi dalam ${jeda} detik` : processing ? 'Memeriksa…' : 'Lacak'}
                </button>

                <p className="text-sm text-slate-600">
                    Belum punya kode lacak?{' '}
                    <Link href="/pengaduan" className="font-medium text-pengaduan-700 hover:underline">
                        Buat pengaduan
                    </Link>
                    .
                </p>
            </form>
        </PublicLayout>
    );
}
