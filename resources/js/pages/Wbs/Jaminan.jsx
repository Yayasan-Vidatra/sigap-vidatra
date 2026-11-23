import { Head } from '@inertiajs/react';
import PenampilBerkas from '../../components/PenampilBerkas';
import PublicLayout from '../../layouts/PublicLayout';

/*
 * H-03 Jaminan Kerahasiaan WBS (US-06, F-01, F-03). Sejak revisi 20-11 tampil setelah kode akses benar, di dalam
 * halaman /wbs (Wbs/Akses), bukan lewat alamat sendiri. Dokumen jaminan kerahasiaan dibaca dari Pengaturan
 * (dikelola di H-19); selama belum ada, teks bawaan di bawah ini yang tampil. Seluruh jalur /wbs tidak memakai
 * sesi, cookie, analitik, atau aset pihak ketiga.
 */
function TeksBawaan() {
    return (
        <div className="flex flex-col gap-4 text-slate-700">
            <div className="flex flex-col gap-1.5">
                <h3 className="font-semibold text-slate-900">Identitas Anda dilindungi</h3>
                <p>
                    Identitas Anda dirahasiakan dan hanya dibuka oleh penelaah yang berwenang. Nama dan kontak disimpan
                    terpisah dan terenkripsi, dan setiap pembukaan identitas tercatat. Halaman WBS tidak mencatat alamat IP,
                    tidak memasang cookie, dan tidak memuat analitik.
                </p>
            </div>
            <div className="flex flex-col gap-1.5">
                <h3 className="font-semibold text-slate-900">Tidak ada tindakan balasan</h3>
                <p>
                    Yayasan melarang tindakan balasan terhadap pelapor yang beriktikad baik, misalnya mutasi, penurunan
                    nilai kinerja, atau intimidasi. Tindakan balasan itu sendiri dapat dilaporkan lewat kanal ini.
                </p>
            </div>
            <div className="flex flex-col gap-1.5">
                <h3 className="font-semibold text-slate-900">Yang dapat dilaporkan</h3>
                <ul className="flex list-disc flex-col gap-1 pl-5">
                    <li>Kecurangan atau penyalahgunaan dana dan aset, termasuk dana BOS.</li>
                    <li>Pungutan tidak sah atau di luar pembukuan.</li>
                    <li>Suap atau gratifikasi.</li>
                    <li>Benturan kepentingan dalam pengadaan.</li>
                    <li>Manipulasi data atau laporan.</li>
                    <li>Tindakan balasan terhadap pelapor.</li>
                </ul>
            </div>
            <div className="flex flex-col gap-1.5">
                <h3 className="font-semibold text-slate-900">Yang dialihkan</h3>
                <p>
                    Laporan kekerasan di sekolah (perundungan, kekerasan fisik, psikis, seksual) diteruskan ke Kepala
                    Sekolah terkait, atau langsung ke Pokja BSAN bila Kepala Sekolah terlibat. Pelanggaran kode etik
                    pendidik dan pegawai, kecurangan penilaian atau ujian, dan penyimpangan kepegawaian dialihkan ke
                    Komite Etik. Laporan tetap bisa dikirim lewat formulir ini.
                </p>
            </div>
            <div className="flex flex-col gap-1.5">
                <h3 className="font-semibold text-slate-900">Simpan kode lacak dan PIN</h3>
                <p>
                    Setelah mengirim, Anda mendapat kode lacak yang hanya tampil sekali, dan memakai PIN yang Anda buat
                    sendiri. Keduanya dipakai untuk memantau laporan dan berkirim pesan dengan Tim WBS di halaman Lacak
                    Laporan WBS. PIN tidak tersimpan dalam bentuk yang bisa dibaca, sehingga tidak dapat dipulihkan.
                </p>
            </div>
        </div>
    );
}

function SaluranAlternatif({ saluran }) {
    const ada = saluran.surel || saluran.telepon || saluran.surat;

    return (
        <section className="flex flex-col gap-3 rounded-2xl border border-garis bg-white p-5 sm:p-7">
            <h2 className="text-lg font-extrabold">Saluran lain</h2>
            <p className="text-slate-700">Selain formulir ini, laporan pelanggaran dapat disampaikan langsung kepada SPI.</p>
            {ada ? (
                <dl className="grid gap-3 sm:grid-cols-3">
                    {saluran.surel && (
                        <div className="rounded-lg bg-slate-50 p-3">
                            <dt className="text-sm text-slate-600">Surel khusus</dt>
                            <dd className="break-words font-medium text-slate-900">{saluran.surel}</dd>
                        </div>
                    )}
                    {saluran.telepon && (
                        <div className="rounded-lg bg-slate-50 p-3">
                            <dt className="text-sm text-slate-600">Telepon/WhatsApp SPI</dt>
                            <dd className="font-medium text-slate-900">{saluran.telepon}</dd>
                        </div>
                    )}
                    {saluran.surat && (
                        <div className="rounded-lg bg-slate-50 p-3">
                            <dt className="text-sm text-slate-600">Surat tertutup</dt>
                            <dd className="whitespace-pre-line font-medium text-slate-900">{saluran.surat}</dd>
                        </div>
                    )}
                </dl>
            ) : (
                <p className="text-sm text-slate-600">Alamat surel, nomor telepon/WhatsApp SPI, dan alamat surat sedang disiapkan.</p>
            )}
            <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Laporan lewat surel, telepon, WhatsApp, atau surat dicatat Tim WBS dengan aturan kerahasiaan yang sama.
            </p>
        </section>
    );
}

export default function Jaminan({ berkasJaminan, saluran, onLanjut, memuat = false, galat = null }) {
    return (
        <PublicLayout lebar="sedang" wbs>
            <Head title="Jaminan Kerahasiaan WBS" />

            <div className="flex flex-col gap-5">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[28px] leading-tight font-extrabold tracking-tight sm:text-[34px]">Pelaporan Pelanggaran (WBS)</h1>
                    <p className="text-slate-600">Baca jaminan kerahasiaan dan perlindungan pelapor berikut sebelum melapor.</p>
                </div>

                <section className="flex flex-col gap-4 rounded-2xl border border-wbs-200 bg-white p-5 sm:p-7">
                    <h2 className="text-lg font-extrabold">Jaminan kerahasiaan dan perlindungan pelapor</h2>
                    {berkasJaminan ? <PenampilBerkas src={berkasJaminan} judul="Dokumen jaminan kerahasiaan WBS" /> : <TeksBawaan />}
                </section>

                <SaluranAlternatif saluran={saluran} />

                {galat && (
                    <p role="alert" className="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                        {galat}
                    </p>
                )}

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <button
                        type="button"
                        onClick={onLanjut}
                        disabled={memuat}
                        className="inline-flex min-h-11 items-center justify-center rounded-xl bg-wbs-700 px-5 py-3 text-center font-bold text-white hover:bg-wbs-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-wbs-700 disabled:opacity-60"
                    >
                        {memuat ? 'Membuka formulir…' : 'Saya mengerti, lanjut melapor'}
                    </button>
                </div>
            </div>
        </PublicLayout>
    );
}
