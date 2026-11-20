import { Head, useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Altcha from '../../components/Altcha';
import TautanDrive from '../../components/TautanDrive';
import UnggahLampiran from '../../components/UnggahLampiran';
import PublicLayout, { Kartu, Kolom, Tombol, gayaIsian } from '../../layouts/PublicLayout';
import { DaftarPesan, Galat, Info, JudulKartu, KartuStatus, PengingatKeluar } from '../Lacak/Detail';

/*
 * Lacak WBS (/wbs/lacak; revisi 20-11). Tanpa sesi dan cookie: kode lacak dan PIN dikirim lewat permintaan HTTP
 * biasa, jawabannya berisi data detail dan token Lacak bertanda tangan yang hanya ada di memori halaman. Setiap
 * jawaban membawa token baru; setelah masa sesi tanpa aktivitas, halaman menutup detail sendiri. Kode lacak tidak
 * pernah ada di URL, dan memuat ulang halaman kembali ke isian kode.
 */
function Masuk({ onMasuk }) {
    const form = useHttp({ kode: '', pin: '', altcha: '' });
    const [perluCaptcha, setPerluCaptcha] = useState(false);
    const [jeda, setJeda] = useState(0);
    const [kunciAltcha, setKunciAltcha] = useState(0);
    const [galatUmum, setGalatUmum] = useState(null);

    useEffect(() => {
        if (jeda <= 0) {
            return undefined;
        }

        const pewaktu = window.setTimeout(() => setJeda((detik) => Math.max(0, detik - 1)), 1000);

        return () => window.clearTimeout(pewaktu);
    }, [jeda]);

    async function kirim(e) {
        e.preventDefault();
        setGalatUmum(null);

        try {
            const hasil = await form.post('/wbs/lacak', {
                // Server mengirim kebutuhan CAPTCHA dan sisa jeda bersama galat isian, karena jalur ini tanpa sesi.
                onError: (galat) => {
                    setPerluCaptcha(Boolean(galat.perlu_captcha));
                    setJeda(Number(galat.sisa_jeda ?? 0));
                },
                onHttpException: (jawaban) => setGalatUmum(jawaban.status === 429 ? 'Terlalu banyak percobaan. Coba lagi dalam satu menit.' : 'Gangguan sementara. Coba lagi.'),
                onNetworkError: () => setGalatUmum('Koneksi terputus. Silakan coba lagi.'),
            });

            if (hasil) {
                onMasuk(hasil);

                return;
            }
        } catch {
            // Galat umum sudah ditampilkan.
        }

        form.setData('altcha', '');
        setKunciAltcha((kunci) => kunci + 1);
    }

    return (
        <PublicLayout wbs>
            <Head title="Lacak Laporan WBS" />

            <div className="mx-auto flex max-w-lg flex-col gap-2">
                <h1 className="text-[28px] leading-tight font-extrabold tracking-tight sm:text-[34px]">Lacak Laporan WBS</h1>
                <p className="text-slate-600">Masukkan kode lacak dan PIN untuk melihat status dan berkirim pesan dengan Tim WBS.</p>
            </div>

            <form onSubmit={kirim} noValidate autoComplete="off" className="mx-auto mt-6 flex max-w-lg flex-col gap-5 rounded-2xl border border-garis bg-white p-5 sm:p-7">
                {galatUmum && (
                    <p role="alert" className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-800">
                        {galatUmum}
                    </p>
                )}
                <Kolom id="kode" label="Kode lacak" galat={form.errors.kode}>
                    <input
                        id="kode"
                        type="text"
                        autoCapitalize="characters"
                        spellCheck={false}
                        placeholder="WBS-XXXX-XXXX"
                        value={form.data.kode}
                        onChange={(e) => form.setData('kode', e.target.value)}
                        aria-invalid={form.errors.kode ? true : undefined}
                        className={`${gayaIsian} font-mono uppercase tracking-wider`}
                    />
                </Kolom>
                <Kolom id="pin" label="PIN" keterangan="PIN 6 angka yang Anda buat saat mengirim laporan." galat={form.errors.pin}>
                    <input
                        id="pin"
                        type="password"
                        inputMode="numeric"
                        maxLength={6}
                        value={form.data.pin}
                        onChange={(e) => form.setData('pin', e.target.value)}
                        aria-describedby="pin-keterangan"
                        className={gayaIsian}
                    />
                </Kolom>
                {perluCaptcha && (
                    <div className="flex flex-col gap-2">
                        <p className="text-sm text-slate-700">Terlalu banyak percobaan. Selesaikan verifikasi berikut.</p>
                        <Altcha key={kunciAltcha} onChange={(payload) => form.setData('altcha', payload)} galat={form.errors.altcha} />
                    </div>
                )}
                <Tombol type="submit" varian="wbs" disabled={form.processing || jeda > 0}>
                    {jeda > 0 ? `Coba lagi dalam ${jeda} detik` : form.processing ? 'Memeriksa…' : 'Lacak'}
                </Tombol>
            </form>
        </PublicLayout>
    );
}

function KirimPesan({ token, tokenUnggahWbs, aturanLampiran, maksTautan, onTerkirim, onBerakhir }) {
    const form = useHttp({ isi: '', lampiran: [], tautan: [], setuju_tautan: false });
    const [sedangMengunggah, setSedangMengunggah] = useState(false);
    const [kunci, setKunci] = useState(0);

    async function kirim(e) {
        e.preventDefault();
        form.transform((isian) => ({ ...isian, token, tautan: isian.tautan.filter((url) => url.trim() !== '') }));

        try {
            const hasil = await form.post('/wbs/lacak/pesan', {
                onHttpException: (jawaban) => {
                    if (jawaban.status === 419) {
                        onBerakhir();
                    }
                },
            });

            if (!hasil) {
                return;
            }

            form.reset();
            setKunci((nilai) => nilai + 1);
            onTerkirim(hasil);
        } catch {
            // Galat isian tampil di bawah kolomnya.
        }
    }

    return (
        <form onSubmit={kirim} noValidate className="flex flex-col gap-2.5 border-t border-garis pt-5">
            <label htmlFor="isi" className="text-[15px] font-semibold">
                Tulis pesan
            </label>
            <textarea
                id="isi"
                rows={3}
                placeholder="Tambahkan informasi atau tanyakan perkembangan"
                value={form.data.isi}
                onChange={(e) => form.setData('isi', e.target.value)}
                aria-invalid={form.errors.isi ? true : undefined}
                className={`${gayaIsian} resize-y`}
            />
            <Galat pesan={form.errors.isi} />
            <UnggahLampiran
                key={kunci}
                kanal="wbs"
                ambilToken={() => tokenUnggahWbs}
                aturan={aturanLampiran}
                onChange={(id, berjalan) => {
                    form.setData('lampiran', id);
                    setSedangMengunggah(berjalan);
                }}
                galat={form.errors.lampiran}
            />
            <TautanDrive
                key={`tautan-${kunci}`}
                warna="wbs"
                tautan={form.data.tautan}
                setuju={form.data.setuju_tautan}
                maks={maksTautan}
                galat={form.errors}
                onChange={(tautan, setuju) => form.setData((isian) => ({ ...isian, tautan, setuju_tautan: setuju }))}
            />
            <Tombol type="submit" varian="wbs" disabled={form.processing || sedangMengunggah} className="self-end max-sm:w-full">
                {form.processing ? 'Mengirim…' : sedangMengunggah ? 'Menunggu unggahan selesai…' : 'Kirim pesan'}
            </Tombol>
        </form>
    );
}

function Detail({ laporan, onPerbarui, onKeluar }) {
    const { kodeLacak, judul, unit, kategori, dibuatPada, status, riwayat, tahapan = [], pesan, masaSesiMenit, berhasil } = laporan;
    const pewaktu = useRef(null);

    // Keluar otomatis setelah masa sesi tanpa aktivitas; token di server juga kedaluwarsa pada saat yang sama.
    useEffect(() => {
        window.clearTimeout(pewaktu.current);
        pewaktu.current = window.setTimeout(() => onKeluar('Halaman Lacak ditutup karena tidak aktif. Masukkan kode lacak dan PIN lagi.'), masaSesiMenit * 60 * 1000);

        return () => window.clearTimeout(pewaktu.current);
    }, [laporan, masaSesiMenit, onKeluar]);

    return (
        <PublicLayout wbs>
            <Head title="Detail Laporan" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-garis bg-white px-5 py-5 sm:px-7">
                    <div className="flex min-w-0 flex-col gap-1">
                        <span className="text-sm text-slate-600">Kode lacak</span>
                        <span className="text-2xl font-extrabold tracking-wide break-all sm:text-[26px]">{kodeLacak}</span>
                    </div>
                    <div className="flex flex-wrap gap-x-7 gap-y-2 text-sm text-slate-600">
                        {unit && <Info label="Unit">{unit}</Info>}
                        {kategori && <Info label="Jenis pelanggaran">{kategori}</Info>}
                        <Info label="Dikirim">{dibuatPada}</Info>
                    </div>
                    <Tombol varian="garis" onClick={() => onKeluar(null)}>
                        Keluar
                    </Tombol>
                </div>

                {berhasil && (
                    <p role="status" className="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 font-medium text-green-800">
                        {berhasil}
                    </p>
                )}

                <KartuStatus status={status} tahapan={tahapan} riwayat={riwayat} warnaGaris="border-wbs-700" />

                <div className="flex flex-wrap items-start gap-6">
                    <Kartu aria-labelledby="judul-pesan" className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
                        <JudulKartu id="judul-pesan">Pesan dengan Tim WBS</JudulKartu>
                        <DaftarPesan pesan={pesan} warnaPelapor="bg-wbs-700" warnaLampiran="text-wbs-50" />
                        {laporan.bolehKirimPesan ? (
                            <KirimPesan
                                token={laporan.tokenLacak}
                                tokenUnggahWbs={laporan.tokenUnggahWbs}
                                aturanLampiran={laporan.aturanLampiran}
                                maksTautan={laporan.maksTautan}
                                onTerkirim={onPerbarui}
                                onBerakhir={() => onKeluar('Halaman Lacak ditutup karena tidak aktif. Masukkan kode lacak dan PIN lagi.')}
                            />
                        ) : (
                            <p className="rounded-xl border border-garis bg-latar px-4 py-3 text-slate-700">
                                Laporan sudah ditutup. Bila ada hal lain yang perlu dilaporkan, silakan buat laporan baru.
                            </p>
                        )}
                    </Kartu>

                    <aside className="flex min-w-0 flex-[1_1_300px] flex-col gap-4">
                        <Kartu as="div" className="flex flex-col gap-2 sm:p-6">
                            <h2 className="text-[17px] font-extrabold">Laporan Anda</h2>
                            <p className="text-[15px] font-semibold break-words">{judul}</p>
                        </Kartu>
                        <PengingatKeluar masaSesiMenit={masaSesiMenit} />
                    </aside>
                </div>
            </div>
        </PublicLayout>
    );
}

export default function Lacak() {
    const [laporan, setLaporan] = useState(null);
    const [pesanKeluar, setPesanKeluar] = useState(null);

    if (laporan) {
        return (
            <Detail
                laporan={laporan}
                onPerbarui={setLaporan}
                onKeluar={(pesan) => {
                    setLaporan(null);
                    setPesanKeluar(pesan);
                }}
            />
        );
    }

    return (
        <>
            {pesanKeluar && (
                <p role="status" className="sr-only">
                    {pesanKeluar}
                </p>
            )}
            <Masuk
                onMasuk={(data) => {
                    setPesanKeluar(null);
                    setLaporan(data);
                }}
            />
        </>
    );
}
