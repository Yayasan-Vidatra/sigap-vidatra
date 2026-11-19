import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Altcha from '../../components/Altcha';
import UnggahLampiran from '../../components/UnggahLampiran';
import PublicLayout, { Bagian, JudulHalaman, Kolom, Tombol, gayaIsian } from '../../layouts/PublicLayout';

/*
 * Draf otomatis khusus Formulir Pengaduan (US-02). Jangan dipakai di formulir WBS: draf di browser
 * meninggalkan jejak laporan di perangkat pelapor.
 */
const KUNCI_DRAF = 'sigap.draf-pengaduan.v1';
const KOLOM_TANPA_DRAF = ['situs_web', 'setuju_ketentuan', 'persetujuan_orang_tua', 'altcha', 'lampiran'];

function bacaDraf() {
    try {
        const teks = window.localStorage.getItem(KUNCI_DRAF);
        return teks ? JSON.parse(teks) : null;
    } catch {
        return null;
    }
}

/** Menyimpan draf; mengembalikan true bila ada isian yang tersimpan. */
function simpanDraf(data) {
    try {
        const isian = Object.fromEntries(Object.entries(data).filter(([kolom]) => !KOLOM_TANPA_DRAF.includes(kolom)));
        const kosong = Object.values(isian).every((nilai) => nilai === '' || nilai === false || (Array.isArray(nilai) && nilai.length === 0));

        if (kosong) {
            window.localStorage.removeItem(KUNCI_DRAF);
            return false;
        }

        window.localStorage.setItem(KUNCI_DRAF, JSON.stringify(isian));
        return true;
    } catch {
        // Penyimpanan browser tidak tersedia (mode privat atau diblokir); formulir tetap bisa dipakai.
        return false;
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

function rentang(nilai) {
    if (nilai.length === 0) {
        return null;
    }

    const min = Math.min(...nilai);
    const maks = Math.max(...nilai);

    return min === maks ? `${min}` : `${min}–${maks}`;
}

const angka = new Intl.NumberFormat('id-ID');

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
    lampiran: [],
};

function Centang({ id, checked, onChange, galat, children }) {
    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="flex min-h-11 cursor-pointer items-start gap-3 py-1">
                <input
                    type="checkbox"
                    id={id}
                    name={id}
                    checked={checked}
                    onChange={onChange}
                    aria-invalid={galat ? true : undefined}
                    className="mt-0.5 size-[22px] shrink-0 accent-pengaduan-700"
                />
                <span className="text-[15px] text-slate-900">{children}</span>
            </label>
            {galat && <p className="text-sm font-medium text-rose-700">{galat}</p>}
        </div>
    );
}

function PilihanJenisPelapor({ jenisPelapor, nilai, onChange, galat }) {
    return (
        <div className="flex flex-col gap-2.5">
            <span id="jenis_pelapor-label" className="text-[15px] font-semibold">
                Anda melapor sebagai<span className="text-rose-700"> *</span>
            </span>
            <div role="radiogroup" aria-labelledby="jenis_pelapor-label" aria-invalid={galat ? true : undefined} className="flex flex-wrap gap-2.5">
                {jenisPelapor.map((item) => {
                    const dipilih = nilai === item.value;

                    return (
                        <label
                            key={item.value}
                            className={`flex min-h-11 cursor-pointer items-center gap-2 rounded-xl px-4 py-2.5 text-[15px] font-semibold ${
                                dipilih ? 'border-2 border-pengaduan-700 bg-pengaduan-50' : 'border-[1.5px] border-slate-300 bg-white hover:bg-slate-50'
                            }`}
                        >
                            <input
                                type="radio"
                                name="jenis_pelapor"
                                id={`jenis_pelapor-${item.value}`}
                                value={item.value}
                                checked={dipilih}
                                onChange={() => onChange(item.value)}
                                className="m-0 size-[18px] accent-pengaduan-700"
                            />
                            {item.label}
                        </label>
                    );
                })}
            </div>
            {galat && (
                <p id="jenis_pelapor-galat" className="text-sm font-medium text-rose-700">
                    {galat}
                </p>
            )}
        </div>
    );
}

function Samping({ unitDipilih, kategoriDipilih, unit, kategori }) {
    const tanggapan = kategoriDipilih ? `${kategoriDipilih.sla_tanggapan_hari}` : rentang(kategori.map((item) => item.sla_tanggapan_hari));
    const kontak = unitDipilih ? (unitDipilih.kontak_darurat ? [unitDipilih] : []) : unit.filter((item) => item.kontak_darurat);

    return (
        <aside className="flex min-w-0 flex-[1_1_300px] flex-col gap-4">
            <div className="flex flex-col gap-2.5 rounded-[18px] bg-wbs-700 p-5 text-white sm:p-6">
                <h2 className="text-[17px] font-extrabold">Mau melaporkan pelanggaran?</h2>
                <p className="text-sm leading-relaxed text-wbs-200">
                    Pungutan liar, penyalahgunaan dana, atau suap dilaporkan lewat WBS, boleh tanpa nama.
                </p>
                <Link href="/wbs" className="inline-flex min-h-11 items-center self-start text-[15px] font-bold text-white underline underline-offset-2">
                    Ke Pelaporan Pelanggaran
                </Link>
            </div>
            <div className="flex flex-col gap-3 rounded-[18px] border border-garis bg-white p-5 sm:p-6">
                <h2 className="text-[17px] font-extrabold">Setelah dikirim</h2>
                <ol className="flex list-decimal flex-col gap-2 pl-5 text-sm leading-relaxed text-slate-600">
                    <li>Anda mendapat kode lacak dan Bukti Lapor PDF.</li>
                    <li>{tanggapan ? `Admin memeriksa dalam ${tanggapan} hari kerja.` : 'Admin memeriksa pengaduan Anda.'}</li>
                    <li>Unit sekolah menindaklanjuti dan meminta konfirmasi Anda.</li>
                </ol>
            </div>
            {kontak.length > 0 && (
                <div className="flex flex-col gap-1.5 rounded-[18px] border border-darurat-200 bg-darurat-50 p-5 sm:p-6">
                    <h2 className="text-[17px] font-extrabold text-darurat-900">Menyangkut keselamatan siswa?</h2>
                    {kontak.map((item) => (
                        <p key={item.id} className="text-sm leading-relaxed text-darurat-900">
                            Hubungi kontak darurat {item.nama}: <strong className="break-words">{item.kontak_darurat}</strong>
                        </p>
                    ))}
                </div>
            )}
        </aside>
    );
}

/*
 * H-02 Formulir Pengaduan (US-02, US-03, US-04). Target waktu dan kontak darurat dari props (SLA kategori dan
 * Pengaturan), berubah mengikuti unit dan kategori yang dipilih.
 */
export default function Formulir({ unit, kategori, jenisPelapor, kataKunciWbs, aturanLampiran, maksKronologi }) {
    const [sedangMengunggah, setSedangMengunggah] = useState(false);
    const form = useForm(isianAwal);
    const { data, setData, errors, processing } = form;
    const siswa = data.jenis_pelapor === 'siswa';
    const [drafDipulihkan, setDrafDipulihkan] = useState(false);
    const [adaDraf, setAdaDraf] = useState(false);
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
            setAdaDraf(true);
        }

        drafSiap.current = true;
    }, []);

    useEffect(() => {
        if (!drafSiap.current) {
            return undefined;
        }

        const jeda = setTimeout(() => setAdaDraf(simpanDraf(data)), 600);

        return () => clearTimeout(jeda);
    }, [data]);

    const pola = useMemo(() => polaKataKunci(kataKunciWbs), [kataKunciWbs]);
    const sarankanWbs = pola !== null && pola.test(`${data.judul} ${data.kronologi}`);
    const kategoriDipilih = kategori.find((item) => String(item.id) === String(data.kategori_id));
    const unitDipilih = unit.find((item) => String(item.id) === String(data.unit_id));

    function buangDraf() {
        hapusDraf();
        form.setData(isianAwal);
        form.clearErrors();
        setDrafDipulihkan(false);
        setAdaDraf(false);
    }

    function atur(nama) {
        return (e) => setData(nama, e.target.type === 'checkbox' ? e.target.checked : e.target.value);
    }

    function properti(nama, keterangan = false) {
        const dijelaskan = [errors[nama] ? `${nama}-galat` : null, keterangan ? `${nama}-keterangan` : null].filter(Boolean).join(' ');

        return {
            id: nama,
            name: nama,
            value: data[nama],
            onChange: atur(nama),
            'aria-invalid': errors[nama] ? true : undefined,
            'aria-describedby': dijelaskan || undefined,
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

            <JudulHalaman remah="Pengaduan" judul="Formulir Pengaduan" keterangan="Sekitar 5 menit. Kolom bertanda * wajib diisi.">
                {adaDraf && (
                    <span role="status" className="inline-flex items-center gap-2 rounded-full bg-green-50 px-3 py-2 text-sm font-semibold text-green-800">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                        Draf tersimpan di perangkat ini
                    </span>
                )}
            </JudulHalaman>

            <div className="flex flex-wrap items-start gap-6">
                <form onSubmit={kirim} noValidate className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
                    {drafDipulihkan && (
                        <div className="flex flex-col gap-3 rounded-2xl border border-garis bg-white p-4 text-sm text-slate-700 sm:flex-row sm:items-center sm:justify-between">
                            <p>Isian sebelumnya dipulihkan dari draf di perangkat ini. Hapus draf bila Anda memakai perangkat bersama.</p>
                            <Tombol varian="garis" onClick={buangDraf} className="shrink-0 px-4 py-2.5 text-sm">
                                Hapus draf
                            </Tombol>
                        </div>
                    )}

                    {Object.keys(errors).length > 0 && (
                        <div role="alert" className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                            {errors.kirim ?? 'Ada isian yang perlu diperbaiki. Isian lain tidak hilang.'}
                        </div>
                    )}

                    <Bagian nomor="1" judul="Tentang Anda">
                        <PilihanJenisPelapor
                            jenisPelapor={jenisPelapor}
                            nilai={data.jenis_pelapor}
                            onChange={(nilai) => setData('jenis_pelapor', nilai)}
                            galat={errors.jenis_pelapor}
                        />
                        <Kolom id="nama" label="Nama lengkap" wajib galat={errors.nama}>
                            <input type="text" autoComplete="name" {...properti('nama')} className={gayaIsian} />
                        </Kolom>

                        {siswa ? (
                            <div className="flex flex-col gap-4 rounded-xl bg-pengaduan-50 p-4">
                                <p className="text-sm text-pengaduan-900">
                                    Pelapor siswa memakai kontak orang tua/wali. Kami akan menghubungi orang tua/wali tentang pengaduan ini.
                                </p>
                                <Kolom id="kontak_orang_tua" label="Nomor HP atau surel orang tua/wali" wajib galat={errors.kontak_orang_tua}>
                                    <input type="text" {...properti('kontak_orang_tua')} className={gayaIsian} />
                                </Kolom>
                                <Centang
                                    id="persetujuan_orang_tua"
                                    checked={data.persetujuan_orang_tua}
                                    onChange={atur('persetujuan_orang_tua')}
                                    galat={errors.persetujuan_orang_tua}
                                >
                                    Orang tua/wali sudah mengetahui dan menyetujui pengaduan ini.<span className="text-rose-700"> *</span>
                                </Centang>
                            </div>
                        ) : (
                            <>
                                <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-4">
                                    <Kolom id="email" label="Surel" galat={errors.email}>
                                        <input type="email" autoComplete="email" placeholder="nama@contoh.com" {...properti('email')} className={gayaIsian} />
                                    </Kolom>
                                    <Kolom id="no_hp" label="Nomor HP/WhatsApp" galat={errors.no_hp}>
                                        <input type="tel" autoComplete="tel" {...properti('no_hp')} className={gayaIsian} />
                                    </Kolom>
                                </div>
                                <p className="text-sm text-slate-600">Isi salah satu kontak. Kode lacak dan kabar perkembangan juga dikirim ke surel bila diisi.</p>
                            </>
                        )}
                    </Bagian>

                    <Bagian nomor="2" judul="Isi pengaduan">
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-4">
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
                            <div className="flex min-w-0 flex-col gap-1.5">
                                <Kolom id="kategori_id" label="Kategori" wajib galat={errors.kategori_id}>
                                    <select {...properti('kategori_id', true)} className={gayaIsian}>
                                        <option value="">Pilih kategori</option>
                                        {kategori.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.nama}
                                            </option>
                                        ))}
                                    </select>
                                </Kolom>
                                <span id="kategori_id-keterangan" aria-live="polite" className="text-[13px] text-slate-600">
                                    {kategoriDipilih
                                        ? `Target selesai kategori ini: ${kategoriDipilih.sla_hari} hari kerja`
                                        : 'Pilih kategori untuk melihat target waktu penyelesaian.'}
                                </span>
                            </div>
                        </div>
                        <Kolom id="judul" label="Judul singkat" wajib galat={errors.judul}>
                            <input type="text" {...properti('judul')} className={gayaIsian} />
                        </Kolom>
                        <div className="flex flex-col gap-1.5">
                            <Kolom
                                id="kronologi"
                                label="Ceritakan apa yang terjadi"
                                wajib
                                keterangan="Apa yang terjadi, siapa yang terlibat, dan apa yang Anda harapkan."
                                galat={errors.kronologi}
                            >
                                <textarea rows={5} maxLength={maksKronologi} {...properti('kronologi', true)} className={`${gayaIsian} resize-y leading-normal`} />
                            </Kolom>
                            <span className="self-end text-[13px] text-slate-600">
                                {angka.format(data.kronologi.length)} / {angka.format(maksKronologi)} karakter
                            </span>
                        </div>
                        {sarankanWbs && (
                            <div role="status" className="flex flex-col gap-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                                <p className="font-semibold">Apakah ini dugaan pelanggaran serius atau kekerasan?</p>
                                <p>
                                    Laporan seperti ini sebaiknya dikirim lewat{' '}
                                    <Link href="/wbs" className="font-semibold text-wbs-700 underline">
                                        Pelaporan Pelanggaran (WBS)
                                    </Link>
                                    , yang lebih rahasia dan boleh anonim. Isian di formulir ini tidak dibawa ke formulir WBS. Bila menyangkut
                                    keselamatan siswa, segera hubungi kontak darurat sekolah.
                                </p>
                                <p>Bila memang keluhan layanan, Anda tetap bisa melanjutkan pengaduan ini.</p>
                            </div>
                        )}
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-4">
                            <Kolom id="waktu_kejadian" label="Kapan terjadi" galat={errors.waktu_kejadian}>
                                <input type="datetime-local" {...properti('waktu_kejadian')} className={gayaIsian} />
                            </Kolom>
                            <Kolom id="lokasi" label="Di mana" galat={errors.lokasi}>
                                <input type="text" placeholder="Contoh: depan kelas 3B" {...properti('lokasi')} className={gayaIsian} />
                            </Kolom>
                        </div>
                        <Kolom
                            id="jabatan_pihak_dikeluhkan"
                            label="Jabatan pihak yang dikeluhkan"
                            keterangan="Misalnya wali kelas, petugas tata usaha. Tidak perlu menulis nama."
                            galat={errors.jabatan_pihak_dikeluhkan}
                        >
                            <input type="text" {...properti('jabatan_pihak_dikeluhkan', true)} className={gayaIsian} />
                        </Kolom>
                    </Bagian>

                    <Bagian nomor="3" judul="Lampiran" tambahan="(opsional)">
                        <UnggahLampiran
                            kanal="pengaduan"
                            aturan={aturanLampiran}
                            onChange={(id, berjalan) => {
                                setData('lampiran', id);
                                setSedangMengunggah(berjalan);
                            }}
                            galat={errors.lampiran}
                        />
                    </Bagian>

                    {/* Honeypot: tidak terlihat oleh manusia, biasanya diisi bot. */}
                    <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
                        <label htmlFor="situs_web">Situs web</label>
                        <input type="text" id="situs_web" tabIndex={-1} autoComplete="off" {...properti('situs_web')} />
                    </div>

                    <div className="flex flex-col gap-4 rounded-2xl border border-garis bg-white p-5 sm:px-7 sm:py-6">
                        <Centang id="setuju_ketentuan" checked={data.setuju_ketentuan} onChange={atur('setuju_ketentuan')} galat={errors.setuju_ketentuan}>
                            Saya menyetujui ketentuan penggunaan dan{' '}
                            <Link href="/privasi" className="font-semibold text-pengaduan-700 underline">
                                kebijakan privasi
                            </Link>
                            .<span className="text-rose-700"> *</span>
                        </Centang>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="min-w-0 flex-[1_1_260px]">
                                <Altcha onChange={(payload) => setData('altcha', payload)} galat={errors.altcha} />
                            </div>
                            <Tombol type="submit" disabled={processing || sedangMengunggah} className="px-7 py-3.5 text-[17px] max-sm:w-full">
                                {processing ? 'Mengirim…' : sedangMengunggah ? 'Menunggu unggahan selesai…' : 'Kirim pengaduan'}
                            </Tombol>
                        </div>
                    </div>
                </form>

                <Samping unitDipilih={unitDipilih} kategoriDipilih={kategoriDipilih} unit={unit} kategori={kategori} />
            </div>
        </PublicLayout>
    );
}
