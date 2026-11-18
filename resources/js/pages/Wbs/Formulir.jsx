import { Head, Link, useHttp } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Altcha from '../../components/Altcha';
import UnggahLampiran from '../../components/UnggahLampiran';
import PublicLayout from '../../layouts/PublicLayout';

/*
 * H-04 Formulir WBS (US-05, UC-02, F-03) dan H-05 Bukti Lapor WBS (US-07).
 *
 * Tanpa draf: isian hanya ada di memori halaman, tidak di localStorage atau riwayat browser. Kiriman memakai
 * permintaan HTTP biasa (useHttp) ke /wbs/lapor yang berjalan tanpa sesi dan cookie; pengganti CSRF adalah
 * token formulir bertanda tangan (diperbarui berkala selama halaman terbuka) dan ALTCHA. Jawaban server
 * berisi kode lacak yang ditampilkan sekali di halaman ini; kode tidak pernah masuk URL.
 */
const gayaIsian =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base text-slate-900 focus:border-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-600/30 aria-invalid:border-rose-500';

const isianAwal = {
    mode: '',
    nama: '',
    email: '',
    no_hp: '',
    kategori_id: '',
    unit_id: '',
    terlapor: [{ nama: '', jabatan: '' }],
    kedudukan_terlapor: '',
    judul: '',
    kronologi: '',
    waktu_kejadian: '',
    lokasi: '',
    saksi: '',
    lampiran: [],
    pin: '',
    pin_confirmation: '',
    situs_web: '',
    altcha: '',
};

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

function polaKataKunci(kataKunci) {
    if (kataKunci.length === 0) {
        return null;
    }

    const daftar = kataKunci.map((kata) => kata.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');

    return new RegExp(`(^|[^\\p{L}\\p{N}])(${daftar})(?:nya|lah|kah|pun)?(?=$|[^\\p{L}\\p{N}])`, 'iu');
}

/*
 * Token formulir baru diminta berkala tanpa cookie (credentials: 'omit'), agar halaman yang lama terbuka
 * tetap bisa mengirim.
 */
function useTokenFormulir(tokenAwal, masaTokenMenit) {
    const token = useRef(tokenAwal);

    async function perbarui() {
        try {
            const jawaban = await fetch('/wbs/lapor/token', { credentials: 'omit', headers: { Accept: 'application/json' } });

            if (jawaban.ok) {
                token.current = (await jawaban.json()).token;
            }
        } catch {
            // Koneksi terputus; dicoba lagi pada putaran berikutnya.
        }
    }

    useEffect(() => {
        const pewaktu = window.setInterval(perbarui, Math.max(1, Math.floor(masaTokenMenit / 3)) * 60 * 1000);

        return () => window.clearInterval(pewaktu);
    }, [masaTokenMenit]);

    return { ambil: () => token.current, perbarui };
}

function PesanKekerasan({ kontakDarurat }) {
    return (
        <div role="status" className="flex flex-col gap-2 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900">
            <p className="font-semibold">Laporan menyangkut kekerasan di sekolah?</p>
            <p>
                Laporan kekerasan (perundungan, kekerasan fisik, psikis, seksual) akan diteruskan ke Kepala Sekolah terkait
                selaku penanggung jawab BSAN, atau langsung ke Pokja BSAN bila Kepala Sekolah terlibat. Anda tetap bisa
                mengirim laporan ini, tanpa perlu kontak orang tua/wali.
            </p>
            <p>Bila menyangkut keselamatan siswa, segera hubungi kontak darurat sekolah:</p>
            {kontakDarurat.length > 0 ? (
                <ul className="grid gap-2 sm:grid-cols-2">
                    {kontakDarurat.map((item) => (
                        <li key={item.unit} className="rounded-lg bg-white px-3 py-2">
                            <span className="block font-medium text-slate-900">{item.unit}</span>
                            <span className="text-slate-700">{item.kontak}</span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>Kontak darurat sekolah belum tersedia.</p>
            )}
        </div>
    );
}

/*
 * H-05 Bukti Lapor WBS: kode lacak tampil sekali dari memori halaman. PDF diunduh lewat token sekali pakai
 * yang dikirim di badan POST, tanpa cookie, lalu disimpan dari blob agar halaman ini tidak berpindah.
 */
function BuktiLaporWbs({ kodeLacak, tokenPdf, masaUnduhPdfMenit, lampiran }) {
    const [tersalin, setTersalin] = useState(false);
    const [pdf, setPdf] = useState({ status: 'siap', pesan: null });

    useEffect(() => {
        function peringatkan(event) {
            event.preventDefault();
            event.returnValue = '';
        }

        window.addEventListener('beforeunload', peringatkan);

        return () => window.removeEventListener('beforeunload', peringatkan);
    }, []);

    async function salin() {
        try {
            await navigator.clipboard.writeText(kodeLacak);
            setTersalin(true);
        } catch {
            setTersalin(false);
        }
    }

    async function unduhPdf() {
        setPdf({ status: 'mengunduh', pesan: null });

        try {
            const jawaban = await fetch('/wbs/bukti/pdf', {
                method: 'POST',
                credentials: 'omit',
                headers: { 'Content-Type': 'application/json', Accept: 'application/pdf, application/json' },
                body: JSON.stringify({ token: tokenPdf }),
            });

            if (!jawaban.ok) {
                const isi = await jawaban.json().catch(() => ({}));
                setPdf({ status: 'gagal', pesan: isi.message ?? 'PDF tidak dapat diunduh. Catat kode lacak di layar.' });

                return;
            }

            const tautan = document.createElement('a');
            tautan.href = URL.createObjectURL(await jawaban.blob());
            tautan.download = 'bukti-lapor-sigap.pdf';
            document.body.appendChild(tautan);
            tautan.click();
            tautan.remove();
            window.setTimeout(() => URL.revokeObjectURL(tautan.href), 10000);
            setPdf({ status: 'selesai', pesan: null });
        } catch {
            setPdf({ status: 'siap', pesan: 'Koneksi terputus. Coba unduh lagi.' });
        }
    }

    return (
        <section className="flex flex-col gap-4 rounded-xl border border-indigo-200 bg-white p-5">
            <h1 className="text-2xl font-bold">Laporan Anda sudah diterima</h1>
            <div role="alert" className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                <p className="font-semibold">Simpan kode lacak ini sekarang.</p>
                <p className="text-sm">
                    Kode hanya tampil sekali dan tidak dapat dipulihkan bila hilang. PIN yang Anda buat tidak ditampilkan
                    ulang dan tidak tercantum di PDF; ingat PIN itu.
                </p>
            </div>
            <div className="flex flex-col gap-2 rounded-lg bg-indigo-50 p-4">
                <span className="text-sm text-indigo-900">Kode lacak</span>
                <span className="font-mono text-2xl font-bold tracking-wider text-indigo-900 sm:text-3xl">{kodeLacak}</span>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={salin}
                        className="rounded-lg border border-indigo-700 px-3 py-2 text-sm font-semibold text-indigo-800 hover:bg-indigo-100"
                    >
                        {tersalin ? 'Kode tersalin' : 'Salin kode'}
                    </button>
                    <button
                        type="button"
                        onClick={unduhPdf}
                        disabled={pdf.status !== 'siap'}
                        className="rounded-lg bg-indigo-700 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-800 disabled:opacity-60"
                    >
                        {pdf.status === 'mengunduh' ? 'Mengunduh…' : pdf.status === 'selesai' ? 'PDF sudah diunduh' : 'Unduh PDF bukti lapor'}
                    </button>
                </div>
                <p className="text-xs text-indigo-900">
                    PDF bisa diunduh satu kali, paling lama {masaUnduhPdfMenit} menit setelah laporan dikirim.
                </p>
                {pdf.pesan && <p className="text-sm text-rose-700">{pdf.pesan}</p>}
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
                        Lampiran sedang diperiksa keamanannya dan dihapus metadatanya. Bila ada berkas yang tidak dapat
                        diterima, pemberitahuannya tampil di halaman Lacak Laporan.
                    </p>
                </div>
            )}
            <p className="text-sm text-slate-700">
                Pantau laporan dan balas pesan Tim WBS di halaman{' '}
                <Link href="/lacak" className="font-medium text-indigo-700 hover:underline">
                    Lacak Laporan
                </Link>{' '}
                dengan kode lacak dan PIN. Laporan WBS tidak dikirimi surel.
            </p>
        </section>
    );
}

export default function Formulir({ token, masaTokenMenit, unit, kategori, kedudukan, kontakDarurat, kataKunciKekerasan, maksTerlapor, aturanLampiran }) {
    const form = useHttp(isianAwal);
    const { data, setData, errors, processing } = form;
    const tokenFormulir = useTokenFormulir(token, masaTokenMenit);
    const [sedangMengunggah, setSedangMengunggah] = useState(false);
    const [kekerasanDipilih, setKekerasanDipilih] = useState(false);
    const [galatUmum, setGalatUmum] = useState(null);
    const [bukti, setBukti] = useState(null);
    const bernama = data.mode === 'bernama';

    const pola = useMemo(() => polaKataKunci(kataKunciKekerasan), [kataKunciKekerasan]);
    const tampilkanPesanKekerasan = kekerasanDipilih || (pola !== null && pola.test(`${data.judul} ${data.kronologi}`));

    function atur(nama) {
        return (e) => setData(nama, e.target.value);
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

    function ubahTerlapor(indeks, kolom, nilai) {
        setData(
            'terlapor',
            data.terlapor.map((item, i) => (i === indeks ? { ...item, [kolom]: nilai } : item)),
        );
    }

    async function kirim(e) {
        e.preventDefault();
        setGalatUmum(null);
        form.transform((isian) => ({ ...isian, token: tokenFormulir.ambil() }));

        try {
            const hasil = await form.post('/wbs/lapor', {
                onError: (galat) => {
                    if (galat.token) {
                        tokenFormulir.perbarui();
                    }
                },
                onHttpException: (jawaban) => {
                    setGalatUmum(
                        jawaban.status === 429
                            ? 'Terlalu banyak pengiriman dalam waktu singkat. Silakan coba lagi dalam satu menit.'
                            : 'Laporan belum terkirim karena gangguan. Isian Anda masih ada; silakan coba lagi.',
                    );
                },
                onNetworkError: () => {
                    setGalatUmum('Koneksi terputus. Isian Anda masih ada; silakan coba lagi.');
                },
            });

            form.reset();
            setBukti(hasil);
            window.scrollTo(0, 0);
        } catch {
            // Galat sudah ditampilkan lewat errors atau galatUmum.
        }
    }

    if (bukti) {
        return (
            <PublicLayout>
                <Head title="Bukti Lapor" />
                <BuktiLaporWbs {...bukti} />
            </PublicLayout>
        );
    }

    const adaGalat = Object.keys(errors).length > 0;

    return (
        <PublicLayout>
            <Head title="Formulir Pelaporan Pelanggaran" />

            <div className="flex flex-col gap-2">
                <h1 className="text-2xl font-bold">Formulir Pelaporan Pelanggaran (WBS)</h1>
                <p className="text-slate-600">
                    Isian bertanda <span className="text-rose-600">*</span> wajib diisi. Isian tidak disimpan sebagai draf
                    di perangkat ini, jadi selesaikan dalam sekali buka. Keluhan layanan sebaiknya lewat{' '}
                    <Link href="/pengaduan" className="font-medium text-teal-700 hover:underline">
                        Formulir Pengaduan
                    </Link>
                    .
                </p>
            </div>

            <form onSubmit={kirim} noValidate autoComplete="off" className="mt-6 flex flex-col gap-5">
                {(adaGalat || galatUmum) && (
                    <div role="alert" className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                        {galatUmum ?? errors.token ?? 'Ada isian yang perlu diperbaiki. Isian lain tidak hilang.'}
                    </div>
                )}

                <Bagian judul="Cara melapor">
                    <div className="flex flex-col gap-2" role="radiogroup" aria-labelledby="mode-label">
                        <span id="mode-label" className="font-medium text-slate-800">
                            Melapor dengan nama atau anonim?<span className="text-rose-600"> *</span>
                        </span>
                        {[
                            { value: 'anonim', label: 'Anonim', keterangan: 'Tanpa nama dan kontak. Satu-satunya jalur komunikasi adalah kode lacak dan PIN.' },
                            { value: 'bernama', label: 'Bernama', keterangan: 'Identitas disimpan terenkripsi dan hanya dapat dibuka Ketua Tim WBS.' },
                        ].map((pilihan) => (
                            <label
                                key={pilihan.value}
                                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${data.mode === pilihan.value ? 'border-indigo-600 bg-indigo-50' : 'border-slate-300'}`}
                            >
                                <input
                                    type="radio"
                                    name="mode"
                                    value={pilihan.value}
                                    checked={data.mode === pilihan.value}
                                    onChange={atur('mode')}
                                    className="mt-1 size-5 shrink-0 accent-indigo-700"
                                />
                                <span>
                                    <span className="block font-medium text-slate-900">{pilihan.label}</span>
                                    <span className="text-sm text-slate-600">{pilihan.keterangan}</span>
                                </span>
                            </label>
                        ))}
                        {errors.mode && <p className="text-sm text-rose-700">{errors.mode}</p>}
                    </div>

                    {bernama && (
                        <div className="flex flex-col gap-4 rounded-lg bg-slate-50 p-4">
                            <Kolom id="nama" label="Nama" wajib galat={errors.nama}>
                                <input type="text" {...properti('nama')} className={gayaIsian} />
                            </Kolom>
                            <p className="text-sm text-slate-600">
                                Kontak boleh dikosongkan. Bila diisi, Tim WBS dapat menghubungi Anda bila perlu. Laporan WBS
                                tidak dikirimi surel otomatis.
                            </p>
                            <Kolom id="email" label="Surel" galat={errors.email}>
                                <input type="email" {...properti('email')} className={gayaIsian} />
                            </Kolom>
                            <Kolom id="no_hp" label="Nomor HP" galat={errors.no_hp}>
                                <input type="tel" {...properti('no_hp')} className={gayaIsian} />
                            </Kolom>
                        </div>
                    )}
                </Bagian>

                <Bagian judul="Pelanggaran yang dilaporkan">
                    <Kolom id="kategori_id" label="Jenis pelanggaran" wajib galat={errors.kategori_id}>
                        <select {...properti('kategori_id')} className={gayaIsian}>
                            <option value="">Pilih jenis pelanggaran</option>
                            {kategori.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama}
                                </option>
                            ))}
                        </select>
                    </Kolom>
                    <Kolom id="unit_id" label="Unit tempat kejadian" wajib galat={errors.unit_id}>
                        <select {...properti('unit_id')} className={gayaIsian}>
                            <option value="">Pilih unit</option>
                            {unit.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama}
                                </option>
                            ))}
                        </select>
                    </Kolom>
                </Bagian>

                <Bagian judul="Pihak terlapor">
                    {data.terlapor.map((item, indeks) => (
                        <div key={indeks} className="flex flex-col gap-3 rounded-lg border border-slate-200 p-4">
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-medium text-slate-800">Terlapor {indeks + 1}</span>
                                {data.terlapor.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => setData('terlapor', data.terlapor.filter((_, i) => i !== indeks))}
                                        className="rounded-lg px-2 py-1 text-sm text-slate-700 hover:bg-slate-100"
                                    >
                                        Hapus
                                    </button>
                                )}
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Kolom
                                    id={`terlapor-${indeks}-nama`}
                                    label="Nama"
                                    wajib
                                    galat={errors[`terlapor.${indeks}.nama`]}
                                >
                                    <input
                                        type="text"
                                        id={`terlapor-${indeks}-nama`}
                                        value={item.nama}
                                        onChange={(e) => ubahTerlapor(indeks, 'nama', e.target.value)}
                                        aria-invalid={errors[`terlapor.${indeks}.nama`] ? true : undefined}
                                        className={gayaIsian}
                                    />
                                </Kolom>
                                <Kolom id={`terlapor-${indeks}-jabatan`} label="Jabatan" galat={errors[`terlapor.${indeks}.jabatan`]}>
                                    <input
                                        type="text"
                                        id={`terlapor-${indeks}-jabatan`}
                                        value={item.jabatan}
                                        onChange={(e) => ubahTerlapor(indeks, 'jabatan', e.target.value)}
                                        className={gayaIsian}
                                    />
                                </Kolom>
                            </div>
                        </div>
                    ))}
                    {errors.terlapor && <p className="text-sm text-rose-700">{errors.terlapor}</p>}
                    <p className="text-sm text-slate-600">Bila tidak tahu namanya, tulis ciri atau sebutannya.</p>
                    {data.terlapor.length < maksTerlapor && (
                        <button
                            type="button"
                            onClick={() => setData('terlapor', [...data.terlapor, { nama: '', jabatan: '' }])}
                            className="self-start rounded-lg border border-slate-300 bg-white px-4 py-2.5 font-semibold text-slate-800 hover:bg-slate-100"
                        >
                            Tambah terlapor
                        </button>
                    )}

                    <Kolom
                        id="kedudukan_terlapor"
                        label="Kedudukan terlapor"
                        wajib
                        keterangan="Menentukan siapa yang menelaah laporan. Bila terlapor lebih dari satu, pilih kedudukan yang tertinggi. Pilih “Tidak tahu” bila Anda tidak yakin."
                        galat={errors.kedudukan_terlapor}
                    >
                        <select {...properti('kedudukan_terlapor')} className={gayaIsian}>
                            <option value="">Pilih kedudukan terlapor</option>
                            {kedudukan.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </Kolom>
                </Bagian>

                <Bagian judul="Kejadian">
                    <Kolom id="judul" label="Judul singkat" wajib galat={errors.judul}>
                        <input type="text" {...properti('judul')} className={gayaIsian} />
                    </Kolom>
                    <Kolom
                        id="kronologi"
                        label="Kronologi"
                        wajib
                        keterangan="Apa yang terjadi, bagaimana caranya, dan mengapa Anda menduga ini pelanggaran. Sebutkan nilai uang atau dokumen bila ada."
                        galat={errors.kronologi}
                    >
                        <textarea rows={7} {...properti('kronologi')} className={gayaIsian} />
                    </Kolom>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Kolom id="waktu_kejadian" label="Kapan terjadi" galat={errors.waktu_kejadian}>
                            <input type="datetime-local" {...properti('waktu_kejadian')} className={gayaIsian} />
                        </Kolom>
                        <Kolom id="lokasi" label="Di mana terjadi" galat={errors.lokasi}>
                            <input type="text" {...properti('lokasi')} className={gayaIsian} />
                        </Kolom>
                    </div>
                    <Kolom
                        id="saksi"
                        label="Pihak lain yang mengetahui (saksi)"
                        keterangan="Boleh dikosongkan."
                        galat={errors.saksi}
                    >
                        <textarea rows={3} {...properti('saksi')} className={gayaIsian} />
                    </Kolom>
                    <label className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            checked={kekerasanDipilih}
                            onChange={(e) => setKekerasanDipilih(e.target.checked)}
                            className="mt-1 size-5 shrink-0 accent-indigo-700"
                        />
                        <span className="text-slate-800">Laporan ini menyangkut kekerasan di sekolah.</span>
                    </label>
                    {tampilkanPesanKekerasan && <PesanKekerasan kontakDarurat={kontakDarurat} />}
                </Bagian>

                <Bagian judul="Bukti (boleh dikosongkan)">
                    <p className="text-sm text-slate-600">
                        Metadata berkas (lokasi GPS, perangkat, nama pembuat) dihapus otomatis, dan isi berkas disimpan
                        terenkripsi.
                    </p>
                    <UnggahLampiran
                        kanal="wbs"
                        aturan={aturanLampiran}
                        ambilToken={tokenFormulir.ambil}
                        onChange={(id, berjalan) => {
                            setData('lampiran', id);
                            setSedangMengunggah(berjalan);
                        }}
                        galat={errors.lampiran}
                    />
                </Bagian>

                <Bagian judul="PIN">
                    <p className="text-sm text-slate-600">
                        Buat PIN 6 angka. PIN dipakai bersama kode lacak untuk membuka laporan, tidak bisa dipulihkan, dan
                        tidak akan ditampilkan lagi. Hindari tanggal lahir atau angka berurutan.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Kolom id="pin" label="PIN" wajib galat={errors.pin}>
                            <input type="password" inputMode="numeric" maxLength={6} autoComplete="new-password" {...properti('pin')} className={gayaIsian} />
                        </Kolom>
                        <Kolom id="pin_confirmation" label="Ulangi PIN" wajib galat={errors.pin_confirmation}>
                            <input
                                type="password"
                                inputMode="numeric"
                                maxLength={6}
                                autoComplete="new-password"
                                {...properti('pin_confirmation')}
                                className={gayaIsian}
                            />
                        </Kolom>
                    </div>
                </Bagian>

                {/* Honeypot: tidak terlihat oleh manusia, biasanya diisi bot. */}
                <div aria-hidden="true" className="absolute -left-[9999px] h-px w-px overflow-hidden">
                    <label htmlFor="situs_web">Situs web</label>
                    <input type="text" id="situs_web" tabIndex={-1} autoComplete="off" {...properti('situs_web')} />
                </div>

                <Altcha onChange={(payload) => setData('altcha', payload)} galat={errors.altcha} />

                <button
                    type="submit"
                    disabled={processing || sedangMengunggah}
                    className="rounded-lg bg-indigo-700 px-4 py-3 font-semibold text-white hover:bg-indigo-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700 disabled:opacity-60"
                >
                    {processing ? 'Mengirim…' : sedangMengunggah ? 'Menunggu unggahan selesai…' : 'Kirim Laporan'}
                </button>
            </form>
        </PublicLayout>
    );
}
