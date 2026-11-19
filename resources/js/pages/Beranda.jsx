import { Head, Link } from '@inertiajs/react';
import TeksBerparagraf from '../components/TeksBerparagraf';
import PublicLayout, { Tombol } from '../layouts/PublicLayout';

function rentangHari({ min, maks }) {
    return min === maks ? `${min} hari kerja` : `${min}–${maks} hari kerja`;
}

/** Nomor telepon yang bisa diketuk; teks lain (mis. nama petugas) tetap ditampilkan tanpa tautan. */
function tautanTelepon(kontak) {
    const angka = kontak.replace(/[^\d+]/g, '');

    return /^\+?\d{6,15}$/.test(angka) ? `tel:${angka}` : null;
}

function Ikon({ d, warna = 'currentColor', ukuran = 24, className = '' }) {
    return (
        <svg
            aria-hidden="true"
            width={ukuran}
            height={ukuran}
            viewBox="0 0 24 24"
            fill="none"
            stroke={warna}
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={`shrink-0 ${className}`}
        >
            {d}
        </svg>
    );
}

const judulBagian = 'text-[26px] leading-tight font-extrabold tracking-tight sm:text-4xl';

function Hero() {
    return (
        <section className="bg-latar">
            <div className="mx-auto flex max-w-[1200px] flex-wrap items-center gap-12 px-4 pt-12 pb-14 sm:px-6 sm:pt-14 sm:pb-16">
                <div className="flex min-w-0 flex-[1_1_420px] flex-col gap-5">
                    <span className="self-start rounded-full bg-pengaduan-100 px-3 py-1.5 text-xs font-bold tracking-wide text-pengaduan-700 sm:text-[13px]">
                        PENGADUAN DAN PELAPORAN PELANGGARAN
                    </span>
                    <h1 className="text-[34px] leading-[1.08] font-extrabold tracking-tight sm:text-5xl lg:text-[54px]">
                        Suara Anda membantu sekolah kita jadi lebih baik.
                    </h1>
                    <p className="max-w-[540px] text-lg leading-relaxed text-slate-600">
                        Sampaikan keluhan layanan atau laporkan dugaan pelanggaran di SD, SMP, SMA YPVDP, dan Pelaksana Harian.
                        Setiap laporan mendapat kode lacak, sehingga Anda bisa memantau perkembangannya kapan saja.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Tombol href="/pengaduan">
                            Kirim pengaduan
                            <Ikon ukuran={18} d={<path d="M5 12h14M13 6l6 6-6 6" />} />
                        </Tombol>
                        <Tombol href="#wbs" biasa varian="garisWbs">
                            Laporkan pelanggaran
                        </Tombol>
                    </div>
                    <Link href="/lacak" className="inline-flex min-h-11 items-center self-start font-semibold text-pengaduan-700 underline underline-offset-2">
                        Sudah punya kode lacak? Lacak laporan Anda
                    </Link>
                </div>
                <div className="relative min-w-0 flex-[1_1_440px]">
                    <picture>
                        <source type="image/webp" srcSet="/img/foto-siswa-900.webp 900w, /img/foto-siswa-1800.webp 1800w" sizes="(min-width: 1024px) 560px, 100vw" />
                        <img
                            src="/img/foto-siswa-900.jpg"
                            srcSet="/img/foto-siswa-900.jpg 900w, /img/foto-siswa-1800.jpg 1800w"
                            sizes="(min-width: 1024px) 560px, 100vw"
                            alt="Siswa SD YPVDP berpakaian adat dalam kegiatan sekolah"
                            width={1800}
                            height={1200}
                            fetchPriority="high"
                            className="block h-72 w-full rounded-3xl object-cover sm:h-[440px]"
                        />
                    </picture>
                    <div className="absolute right-4 bottom-4 left-4 flex max-w-80 items-center gap-3 rounded-2xl bg-white px-4 py-3.5 shadow-[0_10px_30px_rgba(15,23,42,0.18)] sm:right-5 sm:bottom-5 sm:left-5">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-green-50">
                            <Ikon ukuran={22} warna="#15803D" d={<path d="M20 6 9 17l-5-5" />} />
                        </span>
                        <span className="flex flex-col gap-0.5 text-[13px] text-slate-600">
                            <span className="text-sm font-bold text-slate-900">Setiap laporan punya kode lacak</span>
                            Pantau statusnya di halaman Lacak
                        </span>
                    </div>
                </div>
            </div>
        </section>
    );
}

function Ciri({ judul, isi, gelap = false }) {
    return (
        <div>
            <dt className="font-bold">{judul}</dt>
            <dd className={`mt-0.5 ${gelap ? 'text-wbs-200' : 'text-slate-600'}`}>{isi}</dd>
        </div>
    );
}

function PilihJalur({ targetPengaduan, targetWbs }) {
    const targetTeks = targetPengaduan
        ? `Tanggapan awal ${rentangHari(targetPengaduan.tanggapan)}; selesai sesuai kategori, ${
              targetPengaduan.selesai.min === targetPengaduan.selesai.maks
                  ? rentangHari(targetPengaduan.selesai)
                  : `paling lama ${targetPengaduan.selesai.maks} hari kerja`
          }.`
        : 'Sesuai kategori pengaduan.';

    return (
        <section className="mx-auto max-w-[1200px] px-4 pt-16 pb-6 sm:px-6 sm:pt-[72px]">
            <div className="mb-8 flex max-w-[680px] flex-col gap-2.5">
                <h2 className={judulBagian}>Pilih jalur yang sesuai</h2>
                <p className="text-[17px] leading-relaxed text-slate-600">
                    Keduanya bisa melampirkan foto, video, atau dokumen dan bisa dilacak. Bedanya ada pada isi laporan dan siapa yang
                    menanganinya.
                </p>
            </div>
            <div className="flex flex-wrap gap-6">
                <article className="flex min-w-0 flex-[1_1_440px] flex-col gap-5 rounded-[20px] border border-pengaduan-200 bg-white p-6 sm:p-8">
                    <div className="flex items-center gap-3.5">
                        <span className="flex size-12 items-center justify-center rounded-2xl bg-pengaduan-100">
                            <Ikon warna="#1E3A9E" d={<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />} />
                        </span>
                        <div>
                            <h3 className="text-[22px] font-extrabold">Pengaduan</h3>
                            <p className="mt-1 text-[15px] text-slate-600">Keluhan tentang layanan sekolah</p>
                        </div>
                    </div>
                    <dl className="grid grid-cols-1 gap-3.5 text-[15px] leading-relaxed">
                        <Ciri judul="Contoh" isi="Fasilitas rusak, layanan administrasi, kebersihan, jadwal kegiatan, komunikasi guru dan orang tua." />
                        <Ciri judul="Siapa yang melapor" isi="Orang tua, siswa (dengan kontak orang tua/wali), alumni, dan masyarakat." />
                        <Ciri judul="Identitas" isi="Nama dan satu kontak wajib diisi, agar petugas bisa menindaklanjuti." />
                        <Ciri judul="Target waktu" isi={targetTeks} />
                    </dl>
                    <Tombol href="/pengaduan" className="mt-auto self-start">
                        Kirim pengaduan
                    </Tombol>
                </article>

                <article id="wbs" className="flex min-w-0 flex-[1_1_440px] scroll-mt-6 flex-col gap-5 rounded-[20px] bg-wbs-700 p-6 text-white sm:p-8">
                    <div className="flex items-center gap-3.5">
                        <span className="flex size-12 items-center justify-center rounded-2xl bg-white/10">
                            <Ikon warna="#ffffff" d={<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />} />
                        </span>
                        <div>
                            <h3 className="text-[22px] font-extrabold">Pelaporan Pelanggaran (WBS)</h3>
                            <p className="mt-1 text-[15px] text-wbs-200">Dugaan pelanggaran serius, ditangani rahasia</p>
                        </div>
                    </div>
                    <dl className="grid grid-cols-1 gap-3.5 text-[15px] leading-relaxed">
                        <Ciri
                            gelap
                            judul="Contoh"
                            isi="Penyalahgunaan dana atau aset (termasuk BOS), pungutan tidak sah, suap atau gratifikasi, benturan kepentingan, manipulasi data, tindakan balasan terhadap pelapor."
                        />
                        <Ciri gelap judul="Siapa yang melapor" isi="Siapa saja, termasuk guru, karyawan, dan penyedia." />
                        <Ciri gelap judul="Identitas" isi="Boleh tanpa nama. Laporan dipantau dengan kode lacak dan PIN." />
                        <Ciri gelap judul="Target waktu" isi={`Ditelaah dalam ${targetWbs.telaah} hari kerja.`} />
                    </dl>
                    <Tombol href="/wbs" varian="putih" className="mt-auto self-start">
                        Baca jaminan kerahasiaan, lalu lapor
                    </Tombol>
                </article>
            </div>
        </section>
    );
}

function Darurat({ kontakDarurat }) {
    return (
        <section aria-labelledby="darurat" className="mx-auto max-w-[1200px] px-4 pt-6 sm:px-6">
            <div className="flex flex-wrap items-center gap-x-8 gap-y-5 rounded-[20px] border border-darurat-200 bg-darurat-50 px-5 py-6 sm:px-7">
                <div className="flex min-w-0 flex-[1_1_320px] items-start gap-3.5">
                    <Ikon
                        ukuran={28}
                        warna="#B42318"
                        className="mt-0.5"
                        d={
                            <>
                                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
                                <path d="M12 9v4M12 17h.01" />
                            </>
                        }
                    />
                    <div>
                        <h2 id="darurat" className="text-xl font-extrabold text-darurat-900">
                            Ada kekerasan atau bahaya di sekolah?
                        </h2>
                        <p className="mt-1 text-[15px] leading-relaxed text-darurat-900">
                            Jangan menunggu. Hubungi kontak darurat unit{kontakDarurat.length > 0 ? ' di samping' : ' sekolah'}. Laporan
                            kekerasan segera diteruskan ke Kepala Sekolah.
                        </p>
                    </div>
                </div>
                {kontakDarurat.length > 0 && (
                    <ul className="grid min-w-0 flex-[2_1_480px] grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-2.5">
                        {kontakDarurat.map((item) => {
                            const tel = tautanTelepon(item.kontak);
                            const isi = (
                                <>
                                    <span className="block text-[13px] text-slate-600">{item.unit}</span>
                                    <span className="text-[15px] font-bold break-words">{item.kontak}</span>
                                </>
                            );

                            return (
                                <li key={item.unit}>
                                    {tel ? (
                                        <a href={tel} className="block min-h-11 rounded-xl border border-darurat-200 bg-white px-3.5 py-3 text-slate-900 hover:bg-darurat-50">
                                            {isi}
                                        </a>
                                    ) : (
                                        <div className="min-h-11 rounded-xl border border-darurat-200 bg-white px-3.5 py-3 text-slate-900">{isi}</div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </section>
    );
}

const langkah = [
    ['Isi formulir', 'Sekitar 5 menit dari HP. Lampirkan foto, video, atau dokumen bila ada. Draf pengaduan tersimpan otomatis.'],
    ['Simpan kode lacak', 'Kode tampil setelah laporan terkirim. Unduh Bukti Lapor dalam bentuk PDF.'],
    ['Petugas menindaklanjuti', 'Pengaduan diteruskan ke unit sekolah terkait; laporan pelanggaran ditelaah Tim WBS.'],
    ['Pantau dan balas', 'Lihat status, berkirim pesan dengan petugas, dan konfirmasi hasil lewat halaman Lacak.'],
];

function CaraKerja() {
    return (
        <section id="cara" className="mx-auto max-w-[1200px] scroll-mt-6 px-4 pt-20 pb-6 sm:px-6">
            <h2 className={`${judulBagian} mb-8`}>Cara kerjanya</h2>
            <ol className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-5">
                {langkah.map(([judul, isi], i) => (
                    <li key={judul} className="flex flex-col gap-2.5 rounded-[18px] bg-latar p-6">
                        <span aria-hidden="true" className="flex size-9 items-center justify-center rounded-full bg-pengaduan-700 font-extrabold text-white">
                            {i + 1}
                        </span>
                        <h3 className="text-lg font-bold">{judul}</h3>
                        <p className="text-[15px] leading-relaxed text-slate-600">{isi}</p>
                    </li>
                ))}
            </ol>
        </section>
    );
}

const perlindungan = [
    [
        'Tanpa jejak di halaman WBS',
        'Tidak mencatat alamat IP, tidak memasang cookie pelacak, tanpa analitik pihak ketiga.',
        <path key="a" d="M17.9 17.9A10 10 0 0 1 6.1 6.1M2 2l20 20M9.9 4.2A10 10 0 0 1 22 12a10 10 0 0 1-2.2 3.9" />,
    ],
    [
        'Disimpan terenkripsi',
        'Identitas, isi laporan pelanggaran, dan lampirannya disandikan saat disimpan.',
        <>
            <rect x="4" y="11" width="16" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
        </>,
    ],
    [
        'Hanya petugas berwenang',
        'Petugas hanya melihat laporan sesuai perannya, dan setiap pembukaan tercatat.',
        <>
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
        </>,
    ],
    [
        'Tidak ditangani yang dilaporkan',
        'Laporan tentang Tim WBS, Pengawas, atau Pembina otomatis diteruskan ke pihak lain.',
        <path key="d" d="M16 3h5v5M21 3l-7 7M8 21H3v-5M3 21l7-7" />,
    ],
];

function Perlindungan() {
    return (
        <section className="mx-auto max-w-[1200px] px-4 pt-20 pb-6 sm:px-6">
            <div className="flex flex-wrap items-start gap-10">
                <div className="flex min-w-0 flex-[1_1_360px] flex-col gap-3">
                    <h2 className={judulBagian}>Identitas Anda terlindungi</h2>
                    <p className="text-[17px] leading-relaxed text-slate-600">
                        Melapor tidak boleh membuat Anda dirugikan. Tindakan balasan terhadap pelapor termasuk pelanggaran dan dapat
                        dilaporkan.
                    </p>
                </div>
                <div className="grid min-w-0 flex-[2_1_560px] grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
                    {perlindungan.map(([judul, isi, ikon]) => (
                        <div key={judul} className="flex gap-3.5 rounded-2xl border border-garis bg-white p-5">
                            <Ikon warna="#1E3A9E" d={ikon} />
                            <div>
                                <h3 className="font-bold">{judul}</h3>
                                <p className="mt-1 text-sm leading-relaxed text-slate-600">{isi}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function TanyaJawab({ tanyaJawab }) {
    if (tanyaJawab.length === 0) {
        return null;
    }

    return (
        <section id="faq" className="mx-auto max-w-[1200px] px-4 pt-20 pb-20 sm:px-6">
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h2 className={judulBagian}>Pertanyaan yang sering diajukan</h2>
                <Link href="/faq" className="inline-flex min-h-11 items-center font-bold text-pengaduan-700 underline underline-offset-2">
                    Lihat semua tanya jawab
                </Link>
            </div>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,320px),1fr))] gap-4">
                {tanyaJawab.map((butir, i) => (
                    <details key={butir.pertanyaan} open={i < 2} className="rounded-2xl border border-garis bg-white px-5 py-4">
                        <summary className="flex min-h-11 cursor-pointer items-center font-bold">{butir.pertanyaan}</summary>
                        <div className="mt-2 text-[15px] leading-relaxed">
                            <TeksBerparagraf teks={butir.jawaban} />
                        </div>
                    </details>
                ))}
            </div>
        </section>
    );
}

/*
 * H-01 Beranda (US-01, F-01). Angka dan kontak tidak ditanam di kode: kontak darurat dari Pengaturan (units),
 * target Pengaduan dari SLA kategori, target telaah WBS dari config, tanya jawab dari Pengaturan.
 */
export default function Beranda({ kontakDarurat, targetPengaduan, targetWbs, tanyaJawab = [] }) {
    return (
        <PublicLayout polos>
            <Head title="Beranda" />
            <div className="bg-white">
                <Hero />
                <PilihJalur targetPengaduan={targetPengaduan} targetWbs={targetWbs} />
                <Darurat kontakDarurat={kontakDarurat} />
                <CaraKerja />
                <Perlindungan />
                <TanyaJawab tanyaJawab={tanyaJawab} />
                {tanyaJawab.length === 0 && <div className="pb-20" />}
            </div>
        </PublicLayout>
    );
}
