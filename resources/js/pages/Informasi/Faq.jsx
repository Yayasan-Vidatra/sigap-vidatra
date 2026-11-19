import { Head, Link } from '@inertiajs/react';
import TeksBerparagraf from '../../components/TeksBerparagraf';
import PublicLayout from '../../layouts/PublicLayout';

export default function Faq({ pertanyaan, targetPengaduan, targetWbs }) {
    return (
        <PublicLayout>
            <Head title="Tanya Jawab (FAQ)" />

            <div className="flex flex-col gap-6">
                <header className="flex flex-col gap-2">
                    <h1 className="text-xl font-bold sm:text-2xl">Tanya Jawab (FAQ)</h1>
                    <p className="text-slate-600">Jawaban singkat tentang cara melapor, melacak laporan, dan kerahasiaan.</p>
                </header>

                {pertanyaan.length === 0 ? (
                    <p className="rounded-xl border border-slate-200 bg-white p-5 text-slate-600">
                        Tanya jawab sedang disiapkan.
                    </p>
                ) : (
                    <section className="flex flex-col gap-3">
                        {pertanyaan.map((butir, i) => (
                            <details key={i} className="group rounded-xl border border-slate-200 bg-white p-4" open={i === 0}>
                                <summary className="cursor-pointer list-none font-semibold text-slate-900 marker:hidden">
                                    <span className="flex items-start justify-between gap-3">
                                        {butir.pertanyaan}
                                        <span aria-hidden="true" className="text-slate-400 group-open:rotate-45">
                                            +
                                        </span>
                                    </span>
                                </summary>
                                <div className="mt-3">
                                    <TeksBerparagraf teks={butir.jawaban} />
                                </div>
                            </details>
                        ))}
                    </section>
                )}

                <section className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5">
                    <h2 className="text-lg font-semibold">Target waktu penanganan</h2>
                    <p className="text-sm text-slate-600">Dihitung dalam hari kerja (Senin sampai Jumat).</p>

                    <h3 className="font-semibold text-teal-800">Pengaduan</h3>
                    {targetPengaduan.length === 0 ? (
                        <p className="text-slate-600">Belum ada kategori pengaduan.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-slate-500">
                                    <tr>
                                        <th className="py-2 pr-3 font-medium">Kategori</th>
                                        <th className="py-2 pr-3 font-medium">Tanggapan awal</th>
                                        <th className="py-2 font-medium">Selesai</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {targetPengaduan.map((baris) => (
                                        <tr key={baris.kategori}>
                                            <td className="py-2 pr-3">{baris.kategori}</td>
                                            <td className="py-2 pr-3">{baris.tanggapan} hari kerja</td>
                                            <td className="py-2">{baris.selesai} hari kerja</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <h3 className="font-semibold text-indigo-800">Pelaporan Pelanggaran (WBS)</h3>
                    <ul className="list-disc space-y-1 pl-5 text-slate-700">
                        <li>Telaah awal: {targetWbs.telaah} hari kerja sejak laporan diterima.</li>
                        <li>
                            Investigasi: {targetWbs.investigasi} hari kerja, dapat diperpanjang {targetWbs.perpanjangan} hari
                            kerja dengan persetujuan.
                        </li>
                    </ul>
                </section>

                <p className="text-sm text-slate-600">
                    Baca juga{' '}
                    <Link href="/privasi" className="font-medium text-teal-700 hover:underline">
                        Kebijakan Privasi
                    </Link>
                    .
                </p>
            </div>
        </PublicLayout>
    );
}
