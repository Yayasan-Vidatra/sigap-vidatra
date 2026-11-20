import { useId, useState } from 'react';
import { gayaIsian } from '../layouts/PublicLayout';

/*
 * Tautan Google Drive sebagai bukti tambahan (revisi 20-11), dipakai Formulir Pengaduan, Formulir WBS, dan pesan
 * lanjutan di Lacak. Isian tautan baru terbuka setelah pelapor membaca peringatan dan menekan "Saya mengerti";
 * persetujuan itu ikut dikirim (setuju_tautan) dan diperiksa lagi di server. "Tidak, saya kompres saja" menutup
 * panel dan menampilkan saran mengompres video atau membuat ZIP.
 *
 * Props: tautan (daftar alamat), setuju (boolean), onChange(tautan, setuju), maks, galat (objek errors dari server),
 * warna ('pengaduan' atau 'wbs').
 */
const PERINGATAN = [
    'Berkas di Google Drive tidak dapat dipindai antivirus oleh sistem.',
    'Berkas tidak dapat dienkripsi oleh sistem.',
    'Jangan hapus berkas atau cabut aksesnya sebelum laporan dinyatakan selesai.',
    'Alamat surel pemilik Drive dapat dilihat petugas.',
    'Bagikan akses ke surel petugas, bukan "siapa saja yang memiliki tautan".',
];

export default function TautanDrive({ tautan, setuju, onChange, maks = 5, galat = {}, warna = 'pengaduan' }) {
    const [tahap, setTahap] = useState(setuju ? 'isi' : 'tutup');
    const id = useId();
    const aksen = warna === 'wbs' ? 'text-wbs-700' : 'text-pengaduan-700';
    const tombolUtama = warna === 'wbs' ? 'bg-wbs-700 hover:bg-wbs-600' : 'bg-pengaduan-700 hover:bg-pengaduan-800';
    const galatTautan = Object.entries(galat)
        .filter(([kunci]) => kunci === 'tautan' || kunci.startsWith('tautan.') || kunci === 'setuju_tautan')
        .map(([, pesan]) => pesan);

    function ubah(indeks, nilai) {
        onChange(
            tautan.map((item, i) => (i === indeks ? nilai : item)),
            setuju,
        );
    }

    if (tahap === 'tutup' || tahap === 'kompres') {
        return (
            <div className="flex flex-col gap-2">
                <button
                    type="button"
                    onClick={() => setTahap('peringatan')}
                    className={`inline-flex min-h-11 items-center self-start text-[15px] font-semibold underline underline-offset-2 ${aksen}`}
                    aria-expanded="false"
                >
                    Berkas terlalu besar? Tempel tautan Google Drive
                </button>
                {tahap === 'kompres' && (
                    <p role="status" className="rounded-xl border border-garis bg-latar px-4 py-3 text-sm leading-relaxed text-slate-700">
                        Coba kompres video dengan aplikasi di HP (misalnya pilih resolusi 720p saat menyimpan atau membagikan), atau
                        satukan beberapa berkas menjadi satu berkas ZIP, lalu unggah di atas.
                    </p>
                )}
            </div>
        );
    }

    if (tahap === 'peringatan') {
        return (
            <section aria-labelledby={`${id}-judul`} className="flex flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 sm:p-5">
                <h3 id={`${id}-judul`} className="font-bold text-amber-950">
                    Sebelum menempel tautan Google Drive
                </h3>
                <ul className="flex list-disc flex-col gap-1.5 pl-5 text-sm leading-relaxed text-amber-950">
                    {PERINGATAN.map((butir) => (
                        <li key={butir}>{butir}</li>
                    ))}
                </ul>
                <div className="flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={() => {
                            setTahap('isi');
                            onChange(tautan.length > 0 ? tautan : [''], true);
                        }}
                        className={`inline-flex min-h-11 items-center rounded-xl px-4 py-2.5 text-[15px] font-bold text-white ${tombolUtama}`}
                    >
                        Saya mengerti
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setTahap('kompres');
                            onChange([], false);
                        }}
                        className="inline-flex min-h-11 items-center rounded-xl border-[1.5px] border-slate-300 bg-white px-4 py-2.5 text-[15px] font-bold text-slate-700 hover:bg-slate-50"
                    >
                        Tidak, saya kompres saja
                    </button>
                </div>
            </section>
        );
    }

    return (
        <fieldset className="flex flex-col gap-2.5 rounded-2xl border border-garis bg-white p-4 sm:p-5">
            <legend className="px-1 text-[15px] font-semibold">Tautan Google Drive</legend>
            <p className="text-sm text-slate-600">
                Paling banyak {maks} tautan, hanya dari drive.google.com atau docs.google.com. Bagikan akses ke surel petugas.
            </p>
            {tautan.map((item, indeks) => (
                <div key={indeks} className="flex flex-wrap items-center gap-2">
                    <label htmlFor={`${id}-tautan-${indeks}`} className="sr-only">
                        Tautan Google Drive {indeks + 1}
                    </label>
                    <input
                        id={`${id}-tautan-${indeks}`}
                        type="url"
                        inputMode="url"
                        placeholder="https://drive.google.com/…"
                        value={item}
                        onChange={(e) => ubah(indeks, e.target.value)}
                        aria-invalid={galat[`tautan.${indeks}`] ? true : undefined}
                        className={`${gayaIsian} min-w-0 flex-[1_1_240px]`}
                    />
                    <button
                        type="button"
                        onClick={() => onChange(tautan.filter((_, i) => i !== indeks), setuju)}
                        className="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-slate-700 underline underline-offset-2"
                    >
                        Hapus
                    </button>
                </div>
            ))}
            {tautan.length < maks && (
                <button
                    type="button"
                    onClick={() => onChange([...tautan, ''], setuju)}
                    className={`inline-flex min-h-11 items-center self-start text-sm font-semibold underline underline-offset-2 ${aksen}`}
                >
                    Tambah tautan
                </button>
            )}
            {galatTautan.length > 0 && (
                <ul className="flex flex-col gap-1 text-sm font-medium text-rose-700">
                    {[...new Set(galatTautan)].map((pesan) => (
                        <li key={pesan}>{pesan}</li>
                    ))}
                </ul>
            )}
        </fieldset>
    );
}
