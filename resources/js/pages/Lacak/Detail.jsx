import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import UnggahLampiran from '../../components/UnggahLampiran';
import PublicLayout from '../../layouts/PublicLayout';

const gayaIsian =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/30 aria-invalid:border-rose-500';

const gayaTombol = 'rounded-lg px-4 py-2.5 font-semibold disabled:cursor-not-allowed disabled:opacity-60';

function Bagian({ judul, children }) {
    return (
        <section className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <h2 className="text-lg font-semibold">{judul}</h2>
            {children}
        </section>
    );
}

function Galat({ id, pesan }) {
    return pesan ? (
        <p id={id} className="text-sm text-rose-700">
            {pesan}
        </p>
    ) : null;
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

function Tanggapan({ batasTanggapan }) {
    const konfirmasi = useForm({});
    const tolak = useForm({ alasan: '' });
    const [menolak, setMenolak] = useState(false);

    return (
        <Bagian judul="Tanggapi hasil penanganan">
            <p className="text-slate-700">
                Apakah masalah Anda sudah terselesaikan? Tanggapi paling lambat {batasTanggapan}. Tanpa tanggapan,
                pengaduan dianggap selesai.
            </p>
            {!menolak ? (
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        disabled={konfirmasi.processing}
                        onClick={() => konfirmasi.post('/lacak/laporan/konfirmasi', { preserveScroll: true })}
                        className={`${gayaTombol} bg-teal-700 text-white hover:bg-teal-800`}
                    >
                        Ya, sudah selesai
                    </button>
                    <button
                        type="button"
                        onClick={() => setMenolak(true)}
                        className={`${gayaTombol} border border-slate-300 text-slate-800 hover:bg-slate-100`}
                    >
                        Belum, tolak hasil
                    </button>
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
                    <label htmlFor="alasan" className="font-medium text-slate-800">
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
                        <button type="submit" disabled={tolak.processing} className={`${gayaTombol} bg-rose-700 text-white hover:bg-rose-800`}>
                            Kirim penolakan
                        </button>
                        <button
                            type="button"
                            onClick={() => setMenolak(false)}
                            className={`${gayaTombol} border border-slate-300 text-slate-800 hover:bg-slate-100`}
                        >
                            Batal
                        </button>
                    </div>
                </form>
            )}
            <Galat pesan={konfirmasi.errors.tanggapan} />
        </Bagian>
    );
}

function Penilaian() {
    const { data, setData, post, processing, errors } = useForm({ nilai: '' });

    return (
        <Bagian judul="Beri penilaian">
            <form
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    post('/lacak/laporan/penilaian', { preserveScroll: true });
                }}
                className="flex flex-col gap-3"
            >
                <fieldset className="flex flex-col gap-2">
                    <legend className="text-slate-700">Seberapa puas Anda dengan penanganan pengaduan ini? (1 = sangat tidak puas, 5 = sangat puas)</legend>
                    <div className="flex flex-wrap gap-2">
                        {[1, 2, 3, 4, 5].map((nilai) => (
                            <label
                                key={nilai}
                                className={`flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg border text-lg font-semibold ${
                                    Number(data.nilai) === nilai ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-300 text-slate-800 hover:bg-slate-100'
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
                <button type="submit" disabled={processing || data.nilai === ''} className={`${gayaTombol} self-start bg-teal-700 text-white hover:bg-teal-800`}>
                    Kirim penilaian
                </button>
            </form>
        </Bagian>
    );
}

function Pesan({ pesan, bolehKirimPesan, aturanLampiran }) {
    const form = useForm({ isi: '', lampiran: [] });
    const [sedangMengunggah, setSedangMengunggah] = useState(false);
    const [kunciUnggah, setKunciUnggah] = useState(0);

    function kirim(e) {
        e.preventDefault();
        form.post('/lacak/laporan/pesan', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setKunciUnggah((kunci) => kunci + 1);
            },
        });
    }

    return (
        <Bagian judul="Pesan dengan petugas">
            {pesan.length === 0 ? (
                <p className="text-slate-600">Belum ada pesan.</p>
            ) : (
                <ol className="flex flex-col gap-3">
                    {pesan.map((item) => (
                        <li
                            key={item.id}
                            className={`flex flex-col gap-1 rounded-lg p-3 ${item.milikPelapor ? 'bg-teal-50 sm:ml-8' : 'bg-slate-100 sm:mr-8'}`}
                        >
                            <span className="text-xs text-slate-600">
                                {item.dari} · {item.waktu}
                            </span>
                            <p className="whitespace-pre-line text-slate-900">{item.isi}</p>
                            {item.lampiran.length > 0 && (
                                <ul className="text-sm text-slate-700">
                                    {item.lampiran.map((nama) => (
                                        <li key={nama}>📎 {nama}</li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {!bolehKirimPesan ? (
                <p className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">
                    Laporan sudah ditutup. Bila ada masalah baru, silakan buat pengaduan baru.
                </p>
            ) : (
            <form onSubmit={kirim} noValidate className="flex flex-col gap-3">
                <label htmlFor="isi" className="font-medium text-slate-800">
                    Tulis pesan
                </label>
                <textarea
                    id="isi"
                    rows={3}
                    value={form.data.isi}
                    onChange={(e) => form.setData('isi', e.target.value)}
                    aria-invalid={form.errors.isi ? true : undefined}
                    className={gayaIsian}
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
                <button
                    type="submit"
                    disabled={form.processing || sedangMengunggah}
                    className={`${gayaTombol} self-start bg-teal-700 text-white hover:bg-teal-800`}
                >
                    {form.processing ? 'Mengirim…' : sedangMengunggah ? 'Menunggu unggahan selesai…' : 'Kirim pesan'}
                </button>
            </form>
            )}
        </Bagian>
    );
}

/*
 * H-07 Detail Laporan. Hanya terbuka dengan izin di sesi; data yang dikirim server sudah tanpa identitas,
 * nama petugas, catatan internal, dan isi tindak lanjut unit.
 */
export default function Detail(props) {
    const { kodeLacak, judul, unit, kategori, dibuatPada, status, riwayat, pesan, bolehMenanggapi, batasTanggapan } = props;
    const { bolehMenilai, penilaian, masaSesiMenit, berhasil, aturanLampiran, bolehKirimPesan } = props;

    useKeluarOtomatis(masaSesiMenit);

    return (
        <PublicLayout>
            {/* Tanpa kode lacak di judul tab, agar tidak tersimpan di riwayat browser. */}
            <Head title="Detail Laporan" />

            <div className="flex flex-col gap-5">
                <div className="flex flex-col gap-3 rounded-xl border border-teal-200 bg-white p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex flex-col gap-1">
                            <span className="text-sm text-slate-600">Kode lacak</span>
                            <span className="font-mono text-xl font-bold tracking-wider text-teal-900">{kodeLacak}</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post('/lacak/keluar')}
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        >
                            Keluar
                        </button>
                    </div>
                    <div className="flex flex-col gap-1">
                        <span className="text-sm text-slate-600">Status terkini</span>
                        <span className="text-lg font-semibold text-slate-900">{status.label}</span>
                        <p className="text-slate-700">{status.penjelasan}</p>
                    </div>
                    <dl className="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-slate-600">Judul</dt>
                            <dd className="text-slate-900">{judul}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-600">Dikirim</dt>
                            <dd className="text-slate-900">{dibuatPada}</dd>
                        </div>
                        {unit && (
                            <div>
                                <dt className="text-slate-600">Unit</dt>
                                <dd className="text-slate-900">{unit}</dd>
                            </div>
                        )}
                        {kategori && (
                            <div>
                                <dt className="text-slate-600">Kategori</dt>
                                <dd className="text-slate-900">{kategori}</dd>
                            </div>
                        )}
                    </dl>
                    <p className="text-xs text-slate-500">
                        Demi keamanan, halaman ini keluar otomatis setelah {masaSesiMenit} menit tidak aktif.
                    </p>
                </div>

                {berhasil && (
                    <p role="status" className="rounded-lg border border-teal-300 bg-teal-50 px-3 py-2 text-teal-900">
                        {berhasil}
                    </p>
                )}

                {bolehMenanggapi && <Tanggapan batasTanggapan={batasTanggapan} />}
                {bolehMenilai && <Penilaian />}
                {penilaian && (
                    <p className="rounded-lg border border-slate-200 bg-white px-4 py-3 text-slate-700">
                        Penilaian Anda: <strong>{penilaian} dari 5</strong>. Terima kasih.
                    </p>
                )}

                <Bagian judul="Riwayat status">
                    <ol className="flex flex-col gap-3">
                        {riwayat.map((item) => (
                            <li key={item.id} className="flex flex-col gap-0.5 border-l-2 border-teal-600 pl-3">
                                <span className="font-medium text-slate-900">{item.status}</span>
                                <span className="text-sm text-slate-600">{item.waktu}</span>
                                {item.catatan && <p className="text-slate-700">{item.catatan}</p>}
                            </li>
                        ))}
                    </ol>
                </Bagian>

                <Pesan pesan={pesan} bolehKirimPesan={bolehKirimPesan} aturanLampiran={aturanLampiran} />
            </div>
        </PublicLayout>
    );
}
