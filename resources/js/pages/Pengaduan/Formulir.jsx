import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Altcha from '../../components/Altcha';
import PublicLayout from '../../layouts/PublicLayout';

/*
 * Draf otomatis khusus Formulir Pengaduan (US-02). Jangan dipakai di formulir WBS: draf di browser
 * meninggalkan jejak laporan di perangkat pelapor.
 */
const KUNCI_DRAF = 'sigap.draf-pengaduan.v1';
const KOLOM_TANPA_DRAF = ['situs_web', 'setuju_ketentuan', 'persetujuan_orang_tua', 'altcha'];

function bacaDraf() {
    try {
        const teks = window.localStorage.getItem(KUNCI_DRAF);
        return teks ? JSON.parse(teks) : null;
    } catch {
        return null;
    }
}

function simpanDraf(data) {
    try {
        const isian = Object.fromEntries(Object.entries(data).filter(([kolom]) => !KOLOM_TANPA_DRAF.includes(kolom)));
        const kosong = Object.values(isian).every((nilai) => nilai === '' || nilai === false);

        if (kosong) {
            window.localStorage.removeItem(KUNCI_DRAF);
        } else {
            window.localStorage.setItem(KUNCI_DRAF, JSON.stringify(isian));
        }
    } catch {
        // Penyimpanan browser tidak tersedia (mode privat atau diblokir); formulir tetap bisa dipakai.
    }
}

function hapusDraf() {
    try {
        window.localStorage.removeItem(KUNCI_DRAF);
    } catch {
        // Abaikan; tidak ada draf yang bisa dihapus.
    }
}

function polaKataKunci(kataKunci) {
    if (kataKunci.length === 0) {
        return null;
    }

    const daftar = kataKunci.map((kata) => kata.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');

    // Kata utuh, boleh diikuti partikel -nya/-lah/-kah/-pun (mis. "kekerasannya"), tetapi "suapan" tidak cocok.
    return new RegExp(`(^|[^\\p{L}\\p{N}])(${daftar})(?:nya|lah|kah|pun)?(?=$|[^\\p{L}\\p{N}])`, 'iu');
}

const isianAwal = {
    unit_id: '',
    kategori_id: '',
    jenis_pelapor: '',
    nama: '',
    email: '',
    no_hp: '',
    kontak_orang_tua: '',
    persetujuan_orang_tua: false,
    judul: '',
    kronologi: '',
    waktu_kejadian: '',
    lokasi: '',
    jabatan_pihak_dikeluhkan: '',
    setuju_ketentuan: false,
    situs_web: '',
    altcha: '',
};

const gayaIsian =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/30 aria-invalid:border-rose-500';

function Kolom({ id, label, wajib = false, keterangan, galat, children }) {
    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="font-medium text-slate-800">
                {label}
                {wajib && <span className="text-rose-600"> *</span>}
            </label>
            {keterangan && (
                <p id={`${id}-keterangan`} className="text-sm text-slate-600">
                    {keterangan}
                </p>
            )}
            {children}
            {galat && (
                <p id={`${id}-galat`} className="text-sm text-rose-700">
                    {galat}
                </p>
            )}
        </div>
    );
}

function Bagian({ judul, children }) {
    return (
        <fieldset className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5">
            <legend className="px-1 text-lg font-semibold">{judul}</legend>
            {children}
        </fieldset>
    );
}

export default function Formulir({ unit, kategori, jenisPelapor, kataKunciWbs }) {
    const form = useForm(isianAwal);
    const { data, setData, errors, processing } = form;
    const siswa = data.jenis_pelapor === 'siswa';
    const [drafDipulihkan, setDrafDipulihkan] = useState(false);
    const drafSiap = useRef(false);

    useEffect(() => {
        const draf = bacaDraf();

        if (draf) {
            const kolomDikenal = Object.keys(isianAwal).filter((kolom) => !KOLOM_TANPA_DRAF.includes(kolom));
            setData({
                ...isianAwal,
                ...Object.fromEntries(kolomDikenal.filter((kolom) => kolom in draf).map((kolom) => [kolom, draf[kolom]])),
            });
            setDrafDipulihkan(true);
        }

        drafSiap.current = true;
    }, []);

    useEffect(() => {
        if (!drafSiap.current) {
            return undefined;
        }

        const jeda = setTimeout(() => simpanDraf(data), 600);

        return () => clearTimeout(jeda);
    }, [data]);

    const pola = useMemo(() => polaKataKunci(kataKunciWbs), [kataKunciWbs]);
    const sarankanWbs = pola !== null && pola.test(`${data.judul} ${data.kronologi}`);

    function buangDraf() {
        hapusDraf();
        form.setData(isianAwal);
        form.clearErrors();
        setDrafDipulihkan(false);
    }

    function atur(nama) {
        return (e) => setData(nama, e.target.type === 'checkbox' ? e.target.checked : e.target.value);
    }

    function properti(nama) {
        return {
            id: nama,
            name: nama,
            value: data[nama],
            onChange: atur(nama),
            'aria-invalid': errors[nama] ? true : undefined,
            'aria-describedby': errors[nama] ? `${nama}-galat` : undefined,
        };
    }

    function kirim(e) {
        e.preventDefault();
        form.post('/pengaduan', {
            preserveScroll: true,
            onSuccess: () => hapusDraf(),
        });
    }

    return (
        <PublicLayout>
            <Head title="Formulir Pengaduan" />

            <div className="flex flex-col gap-2">
                <h1 className="text-2xl font-bold">Formulir Pengaduan</h1>
                <p className="text-slate-600">
                    Untuk keluhan layanan sekolah atau yayasan. Isian bertanda <span className="text-rose-600">*</span>{' '}
                    wajib diisi. Dugaan pelanggaran serius sebaiknya dilaporkan lewat{' '}
                    <Link href="/wbs" className="font-medium text-indigo-700 hover:underline">
                        Pelaporan Pelanggaran (WBS)
                    </Link>
                    .
                </p>
            </div>

            <form onSubmit={kirim} noValidate className="mt-6 flex flex-col gap-5">
                {drafDipulihkan && (
                    <div className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-700 sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            Isian sebelumnya dipulihkan dari draf di perangkat ini. Hapus draf bila Anda memakai
                            perangkat bersama.
                        </p>
                        <button
                            type="button"
                            onClick={buangDraf}
                            className="shrink-0 rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-800 hover:bg-slate-100"
                        >
                            Hapus draf
                        </button>
                    </div>
                )}

                {Object.keys(errors).length > 0 && (
                    <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        {errors.kirim ?? 'Ada isian yang perlu diperbaiki. Isian lain tidak hilang.'}
                    </div>
                )}

                <Bagian judul="Tujuan pengaduan">
                    <Kolom id="unit_id" label="Unit" wajib galat={errors.unit_id}>
                        <select {...properti('unit_id')} className={gayaIsian}>
                            <option value="">Pilih unit</option>
                            {unit.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama}
                                </option>
                            ))}
                        </select>
                    </Kolom>
                    <Kolom id="kategori_id" label="Kategori" wajib galat={errors.kategori_id}>
                        <select {...properti('kategori_id')} className={gayaIsian}>
                            <option value="">Pilih kategori</option>
                            {kategori.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama}
                                </option>
                            ))}
                        </select>
                    </Kolom>
                </Bagian>

                <Bagian judul="Data pelapor">
                    <Kolom id="jenis_pelapor" label="Saya adalah" wajib galat={errors.jenis_pelapor}>
                        <select {...properti('jenis_pelapor')} className={gayaIsian}>
                            <option value="">Pilih jenis pelapor</option>
                            {jenisPelapor.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </Kolom>
                    <Kolom id="nama" label="Nama" wajib galat={errors.nama}>
                        <input type="text" autoComplete="name" {...properti('nama')} className={gayaIsian} />
                    </Kolom>

                    {siswa ? (
                        <div className="flex flex-col gap-4 rounded-lg bg-teal-50 p-4">
                            <p className="text-sm text-teal-900">
                                Pelapor siswa memakai kontak orang tua/wali. Kami akan menghubungi orang tua/wali
                                tentang pengaduan ini.
                            </p>
                            <Kolom
                                id="kontak_orang_tua"
                                label="Nomor HP atau surel orang tua/wali"
                                wajib
                                galat={errors.kontak_orang_tua}
                            >
                                <input type="text" {...properti('kontak_orang_tua')} className={gayaIsian} />
                            </Kolom>
                            <div className="flex flex-col gap-1.5">
                                <label className="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        id="persetujuan_orang_tua"
                                        name="persetujuan_orang_tua"
                                        checked={data.persetujuan_orang_tua}
                                        onChange={atur('persetujuan_orang_tua')}
                                        aria-invalid={errors.persetujuan_orang_tua ? true : undefined}
                                        className="mt-1 size-5 shrink-0 accent-teal-700"
                                    />
                                    <span className="text-slate-800">
                                        Orang tua/wali sudah mengetahui dan menyetujui pengaduan ini.
                                        <span className="text-rose-600"> *</span>
                                    </span>
                                </label>
                                {errors.persetujuan_orang_tua && (
                                    <p className="text-sm text-rose-700">{errors.persetujuan_orang_tua}</p>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-4">
                            <p className="text-sm text-slate-600">
                                Isi salah satu atau keduanya. Surel dipakai untuk mengirim konfirmasi dan kabar
                                perkembangan.
                            </p>
                            <Kolom id="email" label="Surel" galat={errors.email}>
                                <input type="email" autoComplete="email" {...properti('email')} className={gayaIsian} />
                            </Kolom>
                            <Kolom id="no_hp" label="Nomor HP" galat={errors.no_hp}>
                                <input type="tel" autoComplete="tel" {...properti('no_hp')} className={gayaIsian} />
                            </Kolom>
                        </div>
                    )}
                </Bagian>

                <Bagian judul="Isi pengaduan">
                    <Kolom id="judul" label="Judul" wajib galat={errors.judul}>
                        <input type="text" {...properti('judul')} className={gayaIsian} />
                    </Kolom>
                    <Kolom
                        id="kronologi"
                        label="Kronologi"
                        wajib
                        keterangan="Ceritakan apa yang terjadi, siapa yang terlibat, dan apa yang Anda harapkan."
                        galat={errors.kronologi}
                    >
                        <textarea rows={6} {...properti('kronologi')} className={gayaIsian} />
                    </Kolom>
                    {sarankanWbs && (
                        <div role="status" className="flex flex-col gap-2 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            <p className="font-semibold">Apakah ini dugaan pelanggaran serius atau kekerasan?</p>
                            <p>
                                Laporan seperti ini sebaiknya dikirim lewat{' '}
                                <Link href="/wbs" className="font-semibold text-indigo-800 underline">
                                    Pelaporan Pelanggaran (WBS)
                                </Link>
                                , yang lebih rahasia dan boleh anonim. Isian di formulir ini tidak dibawa ke formulir
                                WBS. Bila menyangkut keselamatan siswa, segera hubungi kontak darurat sekolah di{' '}
                                <Link href="/" className="font-semibold underline">
                                    Beranda
                                </Link>
                                .
                            </p>
                            <p>Bila memang keluhan layanan, Anda tetap bisa melanjutkan pengaduan ini.</p>
                        </div>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Kolom id="waktu_kejadian" label="Waktu kejadian" galat={errors.waktu_kejadian}>
                            <input type="datetime-local" {...properti('waktu_kejadian')} className={gayaIsian} />
                        </Kolom>
                        <Kolom id="lokasi" label="Lokasi" galat={errors.lokasi}>
                            <input type="text" {...properti('lokasi')} className={gayaIsian} />
                        </Kolom>
                    </div>
                    <Kolom
                        id="jabatan_pihak_dikeluhkan"
                        label="Jabatan pihak yang dikeluhkan"
                        keterangan="Misalnya wali kelas, petugas tata usaha. Tidak perlu menulis nama."
                        galat={errors.jabatan_pihak_dikeluhkan}
                    >
                        <input type="text" {...properti('jabatan_pihak_dikeluhkan')} className={gayaIsian} />
                    </Kolom>
                </Bagian>

                {/* Honeypot: tidak terlihat oleh manusia, biasanya diisi bot. */}
                <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
                    <label htmlFor="situs_web">Situs web</label>
                    <input type="text" id="situs_web" tabIndex={-1} autoComplete="off" {...properti('situs_web')} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <label className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            id="setuju_ketentuan"
                            name="setuju_ketentuan"
                            checked={data.setuju_ketentuan}
                            onChange={atur('setuju_ketentuan')}
                            aria-invalid={errors.setuju_ketentuan ? true : undefined}
                            className="mt-1 size-5 shrink-0 accent-teal-700"
                        />
                        <span className="text-slate-800">
                            Saya menyetujui ketentuan penggunaan dan{' '}
                            <Link href="/privasi" className="font-medium text-teal-700 hover:underline">
                                kebijakan privasi
                            </Link>
                            .<span className="text-rose-600"> *</span>
                        </span>
                    </label>
                    {errors.setuju_ketentuan && <p className="text-sm text-rose-700">{errors.setuju_ketentuan}</p>}
                </div>

                <Altcha onChange={(payload) => setData('altcha', payload)} galat={errors.altcha} />

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-lg bg-teal-700 px-4 py-3 font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:opacity-60"
                >
                    {processing ? 'Mengirim…' : 'Kirim Pengaduan'}
                </button>
            </form>
        </PublicLayout>
    );
}
