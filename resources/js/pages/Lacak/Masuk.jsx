import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Altcha from '../../components/Altcha';
import PublicLayout from '../../layouts/PublicLayout';

const gayaIsian =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/30 aria-invalid:border-rose-500';

/*
 * H-06 Lacak Laporan. Kode tidak ada dan kode salah mendapat pesan yang sama dari server. Setelah 5 kali
 * gagal, server meminta CAPTCHA dan jeda; isian PIN untuk WBS disiapkan (kanal WBS dibuat Hari 9).
 */
export default function Masuk({ perluCaptcha, sisaJeda, pesan }) {
    const { data, setData, post, processing, errors } = useForm({ kode: '', pin: '', altcha: '' });
    const [jeda, setJeda] = useState(sisaJeda);
    const wbs = data.kode.trim().toUpperCase().startsWith('WBS-');

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
                <h1 className="text-2xl font-bold">Lacak Laporan</h1>
                <p className="text-slate-600">
                    Masukkan kode lacak dari bukti lapor untuk melihat status, riwayat, dan pesan dari petugas.
                </p>
            </div>

            <form onSubmit={kirim} noValidate className="mx-auto mt-6 flex max-w-lg flex-col gap-5 rounded-xl border border-slate-200 bg-white p-5">
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

                {wbs && (
                    <div className="flex flex-col gap-1.5">
                        <label htmlFor="pin" className="font-medium text-slate-800">
                            PIN
                        </label>
                        <input
                            id="pin"
                            name="pin"
                            type="password"
                            inputMode="numeric"
                            autoComplete="off"
                            value={data.pin}
                            onChange={(e) => setData('pin', e.target.value)}
                            className={gayaIsian}
                        />
                        <p className="text-sm text-slate-600">
                            Laporan pelanggaran (WBS) dilacak dengan kode dan PIN. Kanal WBS sedang disiapkan.
                        </p>
                    </div>
                )}

                {perluCaptcha && (
                    <div className="flex flex-col gap-2">
                        <p className="text-sm text-slate-700">Terlalu banyak percobaan. Selesaikan verifikasi berikut.</p>
                        <Altcha onChange={(payload) => setData('altcha', payload)} galat={errors.altcha} />
                    </div>
                )}

                <button
                    type="submit"
                    disabled={processing || jeda > 0}
                    className="rounded-lg bg-teal-700 px-4 py-3 font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:bg-slate-400"
                >
                    {jeda > 0 ? `Coba lagi dalam ${jeda} detik` : processing ? 'Memeriksa…' : 'Lacak'}
                </button>

                <p className="text-sm text-slate-600">
                    Belum punya kode lacak?{' '}
                    <Link href="/pengaduan" className="font-medium text-teal-700 hover:underline">
                        Buat pengaduan
                    </Link>
                    .
                </p>
            </form>
        </PublicLayout>
    );
}
