import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import TautanDrive from '../../components/TautanDrive';
import UnggahLampiran from '../../components/UnggahLampiran';
import PublicLayout, { Kartu, Tombol, gayaIsian } from '../../layouts/PublicLayout';

export function Galat({ id, pesan }) {
    return pesan ? (
        <p id={id} className="text-sm font-medium text-rose-700">
            {pesan}
        </p>
    ) : null;
}

export function JudulKartu({ id, children }) {
    return (
        <h2 id={id} className="text-[19px] font-extrabold">
            {children}
        </h2>
    );
}

/*
 * Keluar otomatis setelah masa sesi tanpa aktivitas: halaman dimuat ulang, lalu server mengarahkan ke H-06
 * karena izin di sesi sudah berakhir. Setiap kunjungan Inertia (kirim pesan, tanggapan) mengulang hitungan.
 */
function useKeluarOtomatis(masaSesiMenit) {
    useEffect(() => {
        let pewaktu;
        const mulai = () => {
            window.clearTimeout(pewaktu);
            pewaktu = window.setTimeout(() => router.visit('/lacak/laporan'), masaSesiMenit * 60 * 1000 + 1000);
        };

        mulai();
        const lepas = router.on('finish', mulai);

        return () => {
            window.clearTimeout(pewaktu);
            lepas();
        };
    }, [masaSesiMenit]);
}

const WARNA_TAHAP = {
    selesai: 'border-pengaduan-700',
    berjalan: 'border-amber-600',
    menunggu: 'border-slate-200',
};

export function Tahapan({ tahapan }) {
    return (
        <ol className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,150px),1fr))] gap-3">
            {tahapan.map((tahap) => (
                <li
                    key={tahap.label}
                    aria-current={tahap.keadaan === 'berjalan' ? 'step' : undefined}
                    className={`flex flex-col gap-2 border-t-4 pt-3 ${WARNA_TAHAP[tahap.keadaan]}`}
                >
                    <span className={`text-[15px] font-bold ${tahap.keadaan === 'menunggu' ? 'text-slate-600' : ''}`}>{tahap.label}</span>
                    <span
                        className={`text-[13px] ${
                            tahap.keadaan === 'berjalan' ? 'font-semibold text-amber-800' : 'text-slate-600'
                        }`}
                    >
                        {tahap.keadaan === 'menunggu' ? 'Menunggu' : tahap.keadaan === 'berjalan' ? (tahap.waktu ?? 'Sedang berjalan') : tahap.waktu}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function Tanggapan({ batasTanggapan }) {
    const konfirmasi = useForm({});
    const tolak = useForm({ alasan: '' });
    const [menolak, setMenolak] = useState(false);

    return (
        <Kartu className="flex flex-col gap-4">
            <JudulKartu>Tanggapi hasil penanganan</JudulKartu>
            <p className="text-slate-700">
                Apakah masalah Anda sudah terselesaikan? Tanggapi paling lambat {batasTanggapan}. Tanpa tanggapan, pengaduan dianggap
                selesai.
            </p>
            {!menolak ? (
                <div className="flex flex-wrap gap-2">
                    <Tombol disabled={konfirmasi.processing} onClick={() => konfirmasi.post('/lacak/laporan/konfirmasi', { preserveScroll: true })}>
                        Ya, sudah selesai
                    </Tombol>
                    <Tombol varian="garis" onClick={() => setMenolak(true)}>
                        Belum, tolak hasil
                    </Tombol>
                </div>
            ) : (
                <form
                    noValidate
                    onSubmit={(e) => {
                        e.preventDefault();
                        tolak.post('/lacak/laporan/tolak', { preserveScroll: true });
                    }}
                    className="flex flex-col gap-3"
                >
                    <label htmlFor="alasan" className="text-[15px] font-semibold">
                        Alasan menolak hasil
                    </label>
                    <textarea
                        id="alasan"
                        rows={3}
                        value={tolak.data.alasan}
                        onChange={(e) => tolak.setData('alasan', e.target.value)}
                        aria-invalid={tolak.errors.alasan ? true : undefined}
                        className={gayaIsian}
                    />
                    <Galat pesan={tolak.errors.alasan} />
                    <div className="flex flex-wrap gap-2">
                        <Tombol type="submit" varian="bahaya" disabled={tolak.processing}>
                            Kirim penolakan
                        </Tombol>
                        <Tombol varian="garis" onClick={() => setMenolak(false)}>
                            Batal
                        </Tombol>
                    </div>
                </form>
            )}
            <Galat pesan={konfirmasi.errors.tanggapan} />
        </Kartu>
    );
}

function Penilaian() {
    const { data, setData, post, processing, errors } = useForm({ nilai: '' });

    return (
        <Kartu className="flex flex-col gap-4">
            <JudulKartu>Beri penilaian</JudulKartu>
            <form
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    post('/lacak/laporan/penilaian', { preserveScroll: true });
                }}
                className="flex flex-col gap-3"
            >
                <fieldset className="flex flex-col gap-2">
                    <legend className="text-slate-700">
                        Seberapa puas Anda dengan penanganan pengaduan ini? (1 = sangat tidak puas, 5 = sangat puas)
                    </legend>
                    <div className="flex flex-wrap gap-2">
                        {[1, 2, 3, 4, 5].map((nilai) => (
                            <label
                                key={nilai}
                                className={`flex size-12 cursor-pointer items-center justify-center rounded-xl border-[1.5px] text-lg font-bold has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-pengaduan-700 ${
                                    Number(data.nilai) === nilai
                                        ? 'border-pengaduan-700 bg-pengaduan-700 text-white'
                                        : 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="nilai"
                                    value={nilai}
                                    checked={Number(data.nilai) === nilai}
                                    onChange={() => setData('nilai', nilai)}
                                    className="sr-only"
                                />
                                {nilai}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <Galat pesan={errors.nilai} />
                <Tombol type="submit" disabled={processing || data.nilai === ''} className="self-start">
                    Kirim penilaian
                </Tombol>
            </form>
        </Kartu>
    );
}

/** Daftar pesan dengan petugas (dipakai Lacak Pengaduan dan Lacak WBS). */
export function DaftarPesan({ pesan, warnaPelapor = 'bg-pengaduan-700', warnaLampiran = 'text-pengaduan-50' }) {
    if (pesan.length === 0) {
        return <p className="text-slate-600">Belum ada pesan.</p>;
    }

    return (
        <ol className="flex flex-col gap-3.5">
            {pesan.map((item) => (
                <li key={item.id} className={`flex max-w-[85%] flex-col gap-1 sm:max-w-[78%] ${item.milikPelapor ? 'items-end self-end' : 'self-start'}`}>
                    <div
                        className={`px-4 py-3 text-[15px] leading-normal ${
                            item.milikPelapor ? `rounded-2xl rounded-br-sm ${warnaPelapor} text-white` : 'rounded-2xl rounded-bl-sm bg-slate-100 text-slate-900'
                        }`}
                    >
                        <p className="break-words whitespace-pre-line">{item.isi}</p>
                        {(item.lampiran.length > 0 || item.jumlahTautan > 0) && (
                            <ul className={`mt-2 text-sm ${item.milikPelapor ? warnaLampiran : 'text-slate-700'}`}>
                                {item.lampiran.map((nama) => (
                                    <li key={nama} className="break-all">
                                        📎 {nama}
                                    </li>
                                ))}
                                {item.jumlahTautan > 0 && <li>🔗 {item.jumlahTautan} tautan Google Drive</li>}
                            </ul>
                        )}
                    </div>
                    <span className="text-xs text-slate-600">
                        {item.dari} · {item.waktu}
                    </span>
                </li>
            ))}
        </ol>
    );
}

/** Status, tahapan, dan catatan untuk pelapor (dipakai Lacak Pengaduan dan Lacak WBS). */
export function KartuStatus({ status, targetSelesai = null, tahapan = [], riwayat, warnaGaris = 'border-pengaduan-700' }) {
    const catatan = riwayat.filter((item) => item.catatan);

    return (
        <Kartu aria-labelledby="status">
            <div className="mb-5 flex flex-wrap items-center gap-3">
                <h1 id="status" className="text-[22px] font-extrabold">
                    {status.label}
                </h1>
                {targetSelesai && (
                    <span className="rounded-full bg-amber-100 px-2.5 py-1.5 text-[13px] font-bold text-amber-900">Target selesai {targetSelesai}</span>
                )}
            </div>
            <p className="mb-5 text-slate-700">{status.penjelasan}</p>
            {tahapan.length > 0 && <Tahapan tahapan={tahapan} />}
            {catatan.length > 0 && (
                <div className="mt-6 flex flex-col gap-3 border-t border-garis pt-5">
                    <h2 className="font-bold">Catatan untuk Anda</h2>
                    <ol className="flex flex-col gap-3">
                        {catatan.map((item) => (
                            <li key={item.id} className={`flex flex-col gap-0.5 border-l-2 pl-3 ${warnaGaris}`}>
                                <span className="text-sm text-slate-600">
                                    {item.status} · {item.waktu}
                                </span>
                                <p className="text-slate-800">{item.catatan}</p>
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </Kartu>
    );
}

function Pesan({ pesan, bolehKirimPesan, aturanLampiran, maksTautan }) {
    const form = useForm({ isi: '', lampiran: [], tautan: [], setuju_tautan: false });
    const [sedangMengunggah, setSedangMengunggah] = useState(false);
    const [kunciUnggah, setKunciUnggah] = useState(0);

    function kirim(e) {
        e.preventDefault();
        form.transform((isian) => ({ ...isian, tautan: isian.tautan.filter((url) => url.trim() !== '') }));
        form.post('/lacak/laporan/pesan', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setKunciUnggah((kunci) => kunci + 1);
            },
        });
    }

    return (
        <Kartu aria-labelledby="judul-pesan" className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
            <JudulKartu id="judul-pesan">Pesan dengan petugas</JudulKartu>
            <DaftarPesan pesan={pesan} />

            {!bolehKirimPesan ? (
                <p className="rounded-xl border border-garis bg-latar px-4 py-3 text-slate-700">
                    Laporan sudah ditutup. Bila ada masalah baru, silakan buat pengaduan baru.
                </p>
            ) : (
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
                        key={kunciUnggah}
                        kanal="pengaduan"
                        aturan={aturanLampiran}
                        onChange={(id, berjalan) => {
                            form.setData('lampiran', id);
                            setSedangMengunggah(berjalan);
                        }}
                        galat={form.errors.lampiran}
                    />
                    <TautanDrive
                        key={`tautan-${kunciUnggah}`}
                        tautan={form.data.tautan}
                        setuju={form.data.setuju_tautan}
                        maks={maksTautan}
                        galat={form.errors}
                        onChange={(tautan, setuju) => form.setData((isian) => ({ ...isian, tautan, setuju_tautan: setuju }))}
                    />
                    <Tombol type="submit" disabled={form.processing || sedangMengunggah} className="self-end max-sm:w-full">
                        {form.processing ? 'Mengirim…' : sedangMengunggah ? 'Menunggu unggahan selesai…' : 'Kirim pesan'}
                    </Tombol>
                </form>
            )}
        </Kartu>
    );
}

export function Info({ label, children }) {
    return (
        <div>
            <div>{label}</div>
            <div className="text-[15px] font-bold text-slate-900">{children}</div>
        </div>
    );
}

/*
 * H-07 Detail Laporan pengaduan. Hanya terbuka dengan izin di sesi; data yang dikirim server sudah tanpa identitas,
 * nama petugas, catatan internal, dan isi tindak lanjut unit. Kode lacak tidak pernah ada di URL. Laporan WBS
 * dilacak di /wbs/lacak (halaman Wbs/Lacak) yang memakai komponen di atas.
 */
export default function Detail(props) {
    const { kodeLacak, judul, unit, kategori, dibuatPada, status, riwayat, tahapan = [], targetSelesai, pesan, bolehMenanggapi } = props;
    const { batasTanggapan, bolehMenilai, penilaian, masaSesiMenit, berhasil, aturanLampiran, bolehKirimPesan, maksTautan } = props;

    useKeluarOtomatis(masaSesiMenit);

    return (
        <PublicLayout>
            {/* Tanpa kode lacak di judul tab, agar tidak tersimpan di riwayat browser. */}
            <Head title="Detail Laporan" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-garis bg-white px-5 py-5 sm:px-7">
                    <div className="flex min-w-0 flex-col gap-1">
                        <span className="text-sm text-slate-600">Kode lacak</span>
                        <span className="text-2xl font-extrabold tracking-wide break-all sm:text-[26px]">{kodeLacak}</span>
                    </div>
                    <div className="flex flex-wrap gap-x-7 gap-y-2 text-sm text-slate-600">
                        {unit && <Info label="Unit">{unit}</Info>}
                        {kategori && <Info label="Kategori">{kategori}</Info>}
                        <Info label="Dikirim">{dibuatPada}</Info>
                    </div>
                    <Tombol varian="garis" onClick={() => router.post('/lacak/keluar')}>
                        Keluar
                    </Tombol>
                </div>

                {berhasil && (
                    <p role="status" className="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 font-medium text-green-800">
                        {berhasil}
                    </p>
                )}

                <KartuStatus status={status} targetSelesai={targetSelesai} tahapan={tahapan} riwayat={riwayat} />

                {bolehMenanggapi && <Tanggapan batasTanggapan={batasTanggapan} />}
                {bolehMenilai && <Penilaian />}
                {penilaian && (
                    <p className="rounded-2xl border border-garis bg-white px-5 py-4 text-slate-700">
                        Penilaian Anda: <strong>{penilaian} dari 5</strong>. Terima kasih.
                    </p>
                )}

                <div className="flex flex-wrap items-start gap-6">
                    <Pesan pesan={pesan} bolehKirimPesan={bolehKirimPesan} aturanLampiran={aturanLampiran} maksTautan={maksTautan} />

                    <aside className="flex min-w-0 flex-[1_1_300px] flex-col gap-4">
                        <Kartu as="div" className="flex flex-col gap-2 sm:p-6">
                            <h2 className="text-[17px] font-extrabold">Pengaduan Anda</h2>
                            <p className="text-[15px] font-semibold break-words">{judul}</p>
                        </Kartu>
                        <PengingatKeluar masaSesiMenit={masaSesiMenit} />
                    </aside>
                </div>
            </div>
        </PublicLayout>
    );
}

export function PengingatKeluar({ masaSesiMenit }) {
    return (
        <div className="flex gap-3 rounded-[18px] bg-pengaduan-50 p-5 text-sm leading-relaxed text-pengaduan-900">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1E3A9E" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 6v6l4 2" />
            </svg>
            <span>
                Demi keamanan, halaman ini keluar otomatis setelah {masaSesiMenit} menit tidak aktif. Kode lacak tidak pernah ditaruh di
                alamat halaman.
            </span>
        </div>
    );
}
