import { Head, Link, useHttp } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Altcha from '../../components/Altcha';
import UnggahLampiran from '../../components/UnggahLampiran';
import PublicLayout, { Bagian, JudulHalaman, Kolom, Tombol, gayaIsian } from '../../layouts/PublicLayout';

/*
 * H-04 Formulir WBS (US-05, UC-02, F-03) dan H-05 Bukti Lapor WBS (US-07). Struktur dan komponen sama dengan
 * Formulir Pengaduan (bagian bernomor, kolom isian bersama), dengan warna WBS.
 *
 * Tanpa draf: isian hanya ada di memori halaman, tidak di localStorage atau riwayat browser. Kiriman memakai
 * permintaan HTTP biasa (useHttp) ke /wbs/lapor yang berjalan tanpa sesi dan cookie; pengganti CSRF adalah
 * token formulir bertanda tangan (diperbarui berkala selama halaman terbuka) dan ALTCHA. Jawaban server
 * berisi kode lacak yang ditampilkan sekali di halaman ini; kode tidak pernah masuk URL.
 */
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

const kisi = 'grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-4';

function polaKataKunci(kataKunci) {
    if (kataKunci.length === 0) {
        return null;
    }

    const daftar = kataKunci.map((kata) => kata.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');

    return new RegExp(`(^|[^\\p{L}\\p{N}])(${daftar})(?:nya|lah|kah|pun)?(?=$|[^\\p{L}\\p{N}])`, 'iu');
}

function Centang({ id, checked, onChange, children }) {
    return (
        <label htmlFor={id} className="flex min-h-11 cursor-pointer items-start gap-3 py-1">
            <input type="checkbox" id={id} checked={checked} onChange={onChange} className="mt-0.5 size-[22px] shrink-0 accent-wbs-700" />
            <span className="text-[15px] text-slate-900">{children}</span>
        </label>
    );
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

/*
 * Kontak darurat dari Pengaturan; unit yang belum punya kontak mendapat kalimat cadangan dari server
 * (Unit::KONTAK_DARURAT_CADANGAN), bukan ajakan menghubungi nomor yang tidak tampil.
 */
function KontakDarurat({ kontakDarurat, jumlahUnit, kontakDaruratCadangan }) {
    return (
        <>
            {kontakDarurat.length > 0 && (
                <ul className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,180px),1fr))] gap-2">
                    {kontakDarurat.map((item) => (
                        <li key={item.unit} className="rounded-xl border border-darurat-200 bg-white px-3.5 py-3">
                            <span className="block text-[13px] text-slate-600">{item.unit}</span>
                            <span className="font-bold break-words text-slate-900">{item.kontak}</span>
                        </li>
                    ))}
                </ul>
            )}
            {kontakDarurat.length < jumlahUnit && (
                <p className="font-semibold">
                    {kontakDarurat.length > 0
                        ? `Unit lain: ${kontakDaruratCadangan.charAt(0).toLowerCase()}${kontakDaruratCadangan.slice(1)}`
                        : kontakDaruratCadangan}
                </p>
            )}
        </>
    );
}

function PesanKekerasan(props) {
    return (
        <div role="status" className="flex flex-col gap-2 rounded-xl border border-darurat-200 bg-darurat-50 p-4 text-sm text-darurat-900">
            <p className="font-semibold">Laporan menyangkut kekerasan di sekolah?</p>
            <p>
                Laporan kekerasan (perundungan, kekerasan fisik, psikis, seksual) akan diteruskan ke Kepala Sekolah terkait selaku
                penanggung jawab BSAN, atau langsung ke Pokja BSAN bila Kepala Sekolah terlibat. Anda tetap bisa mengirim laporan ini,
                tanpa perlu kontak orang tua/wali.
            </p>
            <p>Bila menyangkut keselamatan siswa, segera minta bantuan:</p>
            <KontakDarurat {...props} />
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
        <section className="flex flex-col gap-4 rounded-2xl border border-wbs-200 bg-white p-5 sm:p-7">
            <h1 className="text-[28px] leading-tight font-extrabold tracking-tight sm:text-[34px]">Laporan Anda sudah diterima</h1>
            <div role="alert" className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                <p className="font-semibold">Simpan kode lacak ini sekarang.</p>
                <p className="text-sm">
                    Kode hanya tampil sekali dan tidak dapat dipulihkan bila hilang. PIN yang Anda buat tidak ditampilkan
                    ulang dan tidak tercantum di PDF; ingat PIN itu.
                </p>
            </div>
            <div className="flex flex-col gap-2 rounded-lg bg-wbs-50 p-4">
                <span className="text-sm text-wbs-900">Kode lacak</span>
                <span className="text-[28px] leading-tight font-extrabold tracking-wide break-all text-wbs-900 sm:text-[34px]">{kodeLacak}</span>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={salin}
                        className="inline-flex min-h-11 items-center justify-center rounded-xl border-[1.5px] border-wbs-700 px-4 py-2.5 text-sm font-semibold text-wbs-800 hover:bg-wbs-100"
                    >
                        {tersalin ? 'Kode tersalin' : 'Salin kode'}
                    </button>
                    <button
                        type="button"
                        onClick={unduhPdf}
                        disabled={pdf.status !== 'siap'}
                        className="inline-flex min-h-11 items-center justify-center rounded-xl bg-wbs-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-wbs-800 disabled:opacity-60"
                    >
                        {pdf.status === 'mengunduh' ? 'Mengunduh…' : pdf.status === 'selesai' ? 'PDF sudah diunduh' : 'Unduh PDF bukti lapor'}
                    </button>
                </div>
                <p className="text-xs text-wbs-900">
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
                <Link href="/lacak" className="font-medium text-wbs-700 hover:underline">
                    Lacak Laporan
                </Link>{' '}
                dengan kode lacak dan PIN. Laporan WBS tidak dikirimi surel.
            </p>
        </section>
    );
}

function Samping({ targetTelaah, kontakDarurat, jumlahUnit, kontakDaruratCadangan }) {
    return (
        <aside className="flex min-w-0 flex-[1_1_300px] flex-col gap-4">
            <div className="flex flex-col gap-2.5 rounded-[18px] bg-wbs-700 p-5 text-white sm:p-6">
                <h2 className="text-[17px] font-extrabold">Kerahasiaan Anda</h2>
                <ul className="flex list-disc flex-col gap-1.5 pl-5 text-sm leading-relaxed text-wbs-200">
                    <li>Halaman ini tidak mencatat alamat IP dan tidak memasang cookie.</li>
                    <li>Isian tidak disimpan sebagai draf di perangkat ini.</li>
                    <li>Identitas, isi laporan, dan bukti disimpan terenkripsi.</li>
                </ul>
            </div>
            <div className="flex flex-col gap-3 rounded-[18px] border border-garis bg-white p-5 sm:p-6">
                <h2 className="text-[17px] font-extrabold">Setelah dikirim</h2>
                <ol className="flex list-decimal flex-col gap-2 pl-5 text-sm leading-relaxed text-slate-600">
                    <li>Anda mendapat kode lacak yang tampil sekali. Simpan bersama PIN Anda.</li>
                    <li>Laporan ditelaah dalam {targetTelaah} hari kerja oleh penelaah yang berwenang.</li>
                    <li>Pantau dan balas pesan Tim WBS di halaman Lacak Laporan.</li>
                </ol>
            </div>
            <div className="flex flex-col gap-2 rounded-[18px] border border-darurat-200 bg-darurat-50 p-5 text-sm text-darurat-900 sm:p-6">
                <h2 className="text-[17px] font-extrabold">Menyangkut keselamatan siswa?</h2>
                <KontakDarurat kontakDarurat={kontakDarurat} jumlahUnit={jumlahUnit} kontakDaruratCadangan={kontakDaruratCadangan} />
            </div>
        </aside>
    );
}

export default function Formulir(props) {
    const { token, masaTokenMenit, unit, kategori, kedudukan, kontakDarurat, kontakDaruratCadangan, targetTelaah } = props;
    const { kataKunciKekerasan, maksTerlapor, aturanLampiran } = props;
    const form = useHttp(isianAwal);
    const { data, setData, errors, processing } = form;
    const tokenFormulir = useTokenFormulir(token, masaTokenMenit);
    const [sedangMengunggah, setSedangMengunggah] = useState(false);
    const [kekerasanDipilih, setKekerasanDipilih] = useState(false);
    const [galatUmum, setGalatUmum] = useState(null);
    const [bukti, setBukti] = useState(null);
    const bernama = data.mode === 'bernama';
    const darurat = { kontakDarurat, jumlahUnit: unit.length, kontakDaruratCadangan };

    const pola = useMemo(() => polaKataKunci(kataKunciKekerasan), [kataKunciKekerasan]);
    const tampilkanPesanKekerasan = kekerasanDipilih || (pola !== null && pola.test(`${data.judul} ${data.kronologi}`));

    function atur(nama) {
        return (e) => setData(nama, e.target.value);
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
            <PublicLayout lebar="sedang">
                <Head title="Bukti Lapor" />
                <BuktiLaporWbs {...bukti} />
            </PublicLayout>
        );
    }

    const adaGalat = Object.keys(errors).length > 0;

    return (
        <PublicLayout>
            <Head title="Formulir Pelaporan Pelanggaran" />

            <JudulHalaman
                remah="Pelaporan Pelanggaran"
                judul="Formulir Pelaporan Pelanggaran"
                keterangan="Kolom bertanda * wajib diisi. Isian tidak disimpan sebagai draf, jadi selesaikan dalam sekali buka."
            />

            <div className="flex flex-wrap items-start gap-6">
                <form onSubmit={kirim} noValidate autoComplete="off" className="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
                    {(adaGalat || galatUmum) && (
                        <div role="alert" className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                            {galatUmum ?? errors.token ?? 'Ada isian yang perlu diperbaiki. Isian lain tidak hilang.'}
                        </div>
                    )}

                    <Bagian nomor="1" judul="Cara melapor" warna="wbs">
                        <div className="flex flex-col gap-2.5" role="radiogroup" aria-labelledby="mode-label">
                            <span id="mode-label" className="text-[15px] font-semibold">
                                Melapor dengan nama atau anonim?<span className="text-rose-700"> *</span>
                            </span>
                            {[
                                { value: 'anonim', label: 'Anonim', keterangan: 'Tanpa nama dan kontak. Satu-satunya jalur komunikasi adalah kode lacak dan PIN.' },
                                { value: 'bernama', label: 'Bernama', keterangan: 'Identitas disimpan terenkripsi dan hanya dapat dibuka penelaah yang berwenang.' },
                            ].map((pilihan) => {
                                const dipilih = data.mode === pilihan.value;

                                return (
                                    <label
                                        key={pilihan.value}
                                        className={`flex min-h-11 cursor-pointer items-start gap-3 rounded-xl p-3.5 ${
                                            dipilih ? 'border-2 border-wbs-700 bg-wbs-50' : 'border-[1.5px] border-slate-300 bg-white hover:bg-slate-50'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="mode"
                                            value={pilihan.value}
                                            checked={dipilih}
                                            onChange={atur('mode')}
                                            className="mt-0.5 size-[18px] shrink-0 accent-wbs-700"
                                        />
                                        <span>
                                            <span className="block text-[15px] font-semibold text-slate-900">{pilihan.label}</span>
                                            <span className="text-sm text-slate-600">{pilihan.keterangan}</span>
                                        </span>
                                    </label>
                                );
                            })}
                            {errors.mode && <p className="text-sm font-medium text-rose-700">{errors.mode}</p>}
                        </div>

                        {bernama && (
                            <>
                                <Kolom id="nama" label="Nama lengkap" wajib galat={errors.nama}>
                                    <input type="text" {...properti('nama')} className={gayaIsian} />
                                </Kolom>
                                <div className={kisi}>
                                    <Kolom id="email" label="Surel" galat={errors.email}>
                                        <input type="email" {...properti('email')} className={gayaIsian} />
                                    </Kolom>
                                    <Kolom id="no_hp" label="Nomor HP/WhatsApp" galat={errors.no_hp}>
                                        <input type="tel" {...properti('no_hp')} className={gayaIsian} />
                                    </Kolom>
                                </div>
                                <p className="text-sm text-slate-600">
                                    Kontak boleh dikosongkan. Bila diisi, Tim WBS dapat menghubungi Anda bila perlu. Laporan WBS tidak dikirimi surel
                                    otomatis.
                                </p>
                            </>
                        )}
                    </Bagian>

                    <Bagian nomor="2" judul="Pelanggaran yang dilaporkan" warna="wbs">
                        <div className={kisi}>
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
                        </div>
                    </Bagian>

                    <Bagian nomor="3" judul="Pihak terlapor" warna="wbs">
                        {data.terlapor.map((item, indeks) => (
                            <div key={indeks} className="flex flex-col gap-4 rounded-xl border border-garis bg-latar p-4">
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-[15px] font-bold">Terlapor {indeks + 1}</span>
                                    {data.terlapor.length > 1 && (
                                        <Tombol
                                            varian="garis"
                                            onClick={() => setData('terlapor', data.terlapor.filter((_, i) => i !== indeks))}
                                            className="px-4 py-2 text-sm"
                                        >
                                            Hapus
                                        </Tombol>
                                    )}
                                </div>
                                <div className={kisi}>
                                    <Kolom id={`terlapor-${indeks}-nama`} label="Nama" wajib galat={errors[`terlapor.${indeks}.nama`]}>
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
                        {errors.terlapor && <p className="text-sm font-medium text-rose-700">{errors.terlapor}</p>}
                        <p className="text-sm text-slate-600">Bila tidak tahu namanya, tulis ciri atau sebutannya.</p>
                        {data.terlapor.length < maksTerlapor && (
                            <Tombol
                                varian="garisWbs"
                                onClick={() => setData('terlapor', [...data.terlapor, { nama: '', jabatan: '' }])}
                                className="self-start"
                            >
                                Tambah terlapor
                            </Tombol>
                        )}
                        <Kolom
                            id="kedudukan_terlapor"
                            label="Kedudukan terlapor"
                            wajib
                            keterangan="Menentukan siapa yang menelaah laporan. Bila terlapor lebih dari satu, pilih kedudukan yang tertinggi. Pilih “Tidak tahu” bila Anda tidak yakin."
                            galat={errors.kedudukan_terlapor}
                        >
                            <select {...properti('kedudukan_terlapor', true)} className={gayaIsian}>
                                <option value="">Pilih kedudukan terlapor</option>
                                {kedudukan.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </Kolom>
                    </Bagian>

                    <Bagian nomor="4" judul="Kejadian" warna="wbs">
                        <Kolom id="judul" label="Judul singkat" wajib galat={errors.judul}>
                            <input type="text" {...properti('judul')} className={gayaIsian} />
                        </Kolom>
                        <Kolom
                            id="kronologi"
                            label="Ceritakan apa yang terjadi"
                            wajib
                            keterangan="Apa yang terjadi, bagaimana caranya, dan mengapa Anda menduga ini pelanggaran. Sebutkan nilai uang atau dokumen bila ada."
                            galat={errors.kronologi}
                        >
                            <textarea rows={6} {...properti('kronologi', true)} className={`${gayaIsian} resize-y leading-normal`} />
                        </Kolom>
                        <div className={kisi}>
                            <Kolom id="waktu_kejadian" label="Kapan terjadi" galat={errors.waktu_kejadian}>
                                <input type="datetime-local" {...properti('waktu_kejadian')} className={gayaIsian} />
                            </Kolom>
                            <Kolom id="lokasi" label="Di mana" galat={errors.lokasi}>
                                <input type="text" {...properti('lokasi')} className={gayaIsian} />
                            </Kolom>
                        </div>
                        <Kolom id="saksi" label="Pihak lain yang mengetahui (saksi)" keterangan="Boleh dikosongkan." galat={errors.saksi}>
                            <textarea rows={3} {...properti('saksi', true)} className={`${gayaIsian} resize-y`} />
                        </Kolom>
                        <Centang id="menyangkut_kekerasan" checked={kekerasanDipilih} onChange={(e) => setKekerasanDipilih(e.target.checked)}>
                            Laporan ini menyangkut kekerasan di sekolah.
                        </Centang>
                        {tampilkanPesanKekerasan && <PesanKekerasan {...darurat} />}
                    </Bagian>

                    <Bagian nomor="5" judul="Bukti" tambahan="(opsional)" warna="wbs">
                        <p className="text-sm text-slate-600">
                            Metadata berkas (lokasi GPS, perangkat, nama pembuat) dihapus otomatis, dan isi berkas disimpan terenkripsi.
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

                    <Bagian nomor="6" judul="PIN" warna="wbs">
                        <p className="text-sm text-slate-600">
                            Buat PIN 6 angka. PIN dipakai bersama kode lacak untuk membuka laporan, tidak bisa dipulihkan, dan tidak akan
                            ditampilkan lagi. Hindari tanggal lahir atau angka berurutan.
                        </p>
                        <div className={kisi}>
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

                    <div className="flex flex-col gap-4 rounded-2xl border border-garis bg-white p-5 sm:px-7 sm:py-6">
                        <p className="text-sm text-slate-600">
                            Keluhan layanan sekolah sebaiknya lewat{' '}
                            <Link href="/pengaduan" className="font-semibold text-pengaduan-700 underline">
                                Formulir Pengaduan
                            </Link>
                            .
                        </p>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="min-w-0 flex-[1_1_260px]">
                                <Altcha onChange={(payload) => setData('altcha', payload)} galat={errors.altcha} />
                            </div>
                            <Tombol type="submit" varian="wbs" disabled={processing || sedangMengunggah} className="px-7 py-3.5 text-[17px] max-sm:w-full">
                                {processing ? 'Mengirim…' : sedangMengunggah ? 'Menunggu unggahan selesai…' : 'Kirim laporan'}
                            </Tombol>
                        </div>
                    </div>
                </form>

                <Samping targetTelaah={targetTelaah} {...darurat} />
            </div>
        </PublicLayout>
    );
}
