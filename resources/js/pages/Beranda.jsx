import { Head, Link } from '@inertiajs/react';
import PublicLayout from '../layouts/PublicLayout';

const kanal = [
    {
        judul: 'Pengaduan',
        tombol: 'Buat Pengaduan',
        href: '/pengaduan',
        warna: 'bg-teal-700 hover:bg-teal-800 focus-visible:outline-teal-700',
        garis: 'border-teal-200',
        untuk: 'Keluhan layanan sekolah atau yayasan.',
        contoh: 'Fasilitas rusak, layanan administrasi lambat, kebersihan, jadwal, komunikasi guru dan orang tua.',
        poin: [
            'Wajib mengisi nama dan kontak. Siswa memakai kontak orang tua/wali.',
            'Ditangani unit atau sekolah terkait.',
        ],
        // Target waktu diambil dari SLA kategori (props targetPengaduan), bukan ditulis di sini.
        denganTarget: true,
    },
    {
        judul: 'Pelaporan Pelanggaran (WBS)',
        tombol: 'Laporkan Pelanggaran',
        href: '/wbs',
        warna: 'bg-indigo-700 hover:bg-indigo-800 focus-visible:outline-indigo-700',
        garis: 'border-indigo-200',
        untuk: 'Dugaan pelanggaran serius.',
        contoh: 'Penyalahgunaan dana atau aset (termasuk BOS), pungutan tidak sah, suap atau gratifikasi, benturan kepentingan, manipulasi data, tindakan balasan terhadap pelapor.',
        poin: [
            'Boleh anonim. Identitas pelapor dirahasiakan.',
            'Ditelaah Tim WBS, tidak diteruskan ke unit yang dilaporkan.',
            'Dilacak dengan kode lacak dan PIN.',
        ],
    },
];

function rentangHari({ min, maks }) {
    return min === maks ? `${min} hari kerja` : `${min}–${maks} hari kerja`;
}

function kalimatTarget(target) {
    if (!target) {
        return null;
    }

    return `Tanggapan awal ${rentangHari(target.tanggapan)}, selesai ${rentangHari(target.selesai)}.`;
}

export default function Beranda({ kontakDarurat, targetPengaduan }) {
    const target = kalimatTarget(targetPengaduan);

    return (
        <PublicLayout>
            <Head title="Beranda" />

            <section className="flex flex-col gap-3">
                <h1 className="text-2xl font-bold sm:text-3xl">Sampaikan keluhan dan laporan Anda</h1>
                <p className="text-slate-600">
                    SIGAP Vidatra adalah pintu resmi Yayasan Vidatra untuk pengaduan layanan dan pelaporan pelanggaran
                    di SD, SMP, SMA YPVDP, dan Pelaksana Harian. Setiap laporan mendapat kode lacak agar Anda bisa
                    memantau perkembangannya.
                </p>
            </section>

            <section className="mt-6 grid gap-4 sm:grid-cols-2">
                {kanal.map((item) => (
                    <article
                        key={item.href}
                        className={`flex flex-col gap-4 rounded-xl border bg-white p-5 shadow-sm ${item.garis}`}
                    >
                        <div className="flex flex-col gap-1">
                            <h2 className="text-lg font-semibold">{item.judul}</h2>
                            <p className="font-medium text-slate-800">{item.untuk}</p>
                            <p className="text-sm text-slate-600">Contoh: {item.contoh}</p>
                        </div>
                        <ul className="flex list-disc flex-col gap-1 pl-5 text-sm text-slate-700">
                            {item.poin.map((poin) => (
                                <li key={poin}>{poin}</li>
                            ))}
                            {item.denganTarget && target && (
                                <li>
                                    {target}{' '}
                                    <Link href="/faq" className="text-teal-700 hover:underline">
                                        Rincian per kategori
                                    </Link>
                                </li>
                            )}
                        </ul>
                        <Link
                            href={item.href}
                            className={`mt-auto rounded-lg px-4 py-3 text-center font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 ${item.warna}`}
                        >
                            {item.tombol}
                        </Link>
                    </article>
                ))}
            </section>

            <section className="mt-6 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="font-semibold">Sudah pernah melapor?</h2>
                    <p className="text-sm text-slate-600">Pantau status laporan dengan kode lacak Anda.</p>
                </div>
                <Link
                    href="/lacak"
                    className="rounded-lg border border-slate-300 px-4 py-3 text-center font-semibold text-slate-800 hover:bg-slate-100"
                >
                    Lacak Laporan
                </Link>
            </section>

            <section className="mt-6 flex flex-col gap-3 rounded-xl border border-rose-200 bg-rose-50 p-5">
                <h2 className="font-semibold text-rose-900">Kontak darurat kekerasan di sekolah</h2>
                <p className="text-sm text-rose-900">
                    Bila ada perundungan atau kekerasan yang membahayakan siswa, segera hubungi kontak darurat sekolah.
                </p>
                {kontakDarurat.length > 0 ? (
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {kontakDarurat.map((item) => (
                            <li key={item.unit} className="rounded-lg bg-white px-4 py-3 text-sm">
                                <span className="block font-medium text-slate-900">{item.unit}</span>
                                <span className="text-slate-700">{item.kontak}</span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-rose-900">Kontak darurat sekolah belum tersedia.</p>
                )}
            </section>
        </PublicLayout>
    );
}
