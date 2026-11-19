import { useEffect, useRef, useState } from 'react';

/*
 * Unggah lampiran bertahap (US-04, F-04) untuk formulir publik, termasuk formulir WBS (H-04).
 * Berkas dipotong di browser dan dikirim per potongan. Potongan yang gagal dicoba ulang sampai 3 kali,
 * sehingga unggahan video tahan koneksi putus-sambung. Format diperiksa server dari isi berkas;
 * pemeriksaan di sini hanya agar pelapor cepat tahu.
 *
 * Kanal pengaduan memakai /lampiran/unggah dengan token CSRF dari cookie. Kanal WBS memakai
 * /wbs/lampiran/unggah tanpa cookie: ambilToken() memberi token formulir WBS yang dikirim di header
 * X-Token-Formulir, dan token CSRF tidak dikirim.
 *
 * onChange(idSiap, sedangMengunggah) menerima id unggahan yang sudah siap dilampirkan, dan apakah
 * masih ada unggahan yang berjalan (formulir sebaiknya menunda pengiriman).
 */
const FORMAT = {
    pengaduan: { terima: '.pdf,.docx,.xlsx,.txt,.jpg,.jpeg,.png,.heic,.mp4,.mov', label: 'PDF, DOCX, XLSX, TXT, JPG, PNG, HEIC, MP4, MOV' },
    wbs: {
        terima: '.pdf,.docx,.xlsx,.txt,.jpg,.jpeg,.png,.heic,.mp4,.mov,.mp3,.m4a',
        label: 'PDF, DOCX, XLSX, TXT, JPG, PNG, HEIC, MP4, MOV, MP3, M4A',
    },
};
const PERCOBAAN_POTONGAN = 3;
const MB = 1024 * 1024;

function tokenCsrf() {
    const cocok = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return cocok ? decodeURIComponent(cocok[1]) : '';
}

function permintaan(metode, url, badan, saatProgres, ambilToken) {
    return new Promise((selesai, gagal) => {
        const xhr = new XMLHttpRequest();
        xhr.open(metode, url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        if (ambilToken) {
            xhr.setRequestHeader('X-Token-Formulir', ambilToken());
        } else {
            xhr.setRequestHeader('X-XSRF-TOKEN', tokenCsrf());
        }

        if (badan && !(badan instanceof FormData)) {
            xhr.setRequestHeader('Content-Type', 'application/json');
        }

        if (saatProgres) {
            xhr.upload.onprogress = (event) => event.lengthComputable && saatProgres(event.loaded);
        }

        xhr.onload = () => {
            let isi = {};

            try {
                isi = xhr.responseText ? JSON.parse(xhr.responseText) : {};
            } catch {
                isi = {};
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                selesai(isi);
            } else {
                gagal({ status: xhr.status, pesan: isi.message ?? 'Unggahan gagal. Silakan coba lagi.' });
            }
        };
        xhr.onerror = () => gagal({ status: 0, pesan: 'Koneksi terputus.' });
        xhr.send(badan instanceof FormData ? badan : badan ? JSON.stringify(badan) : null);
    });
}

const tunggu = (ms) => new Promise((selesai) => setTimeout(selesai, ms));

async function unggahBerkas(berkas, kanal, alamat, ambilToken, saatProgres) {
    const mulai = await permintaan('POST', alamat, { nama: berkas.name, ukuran: berkas.size, kanal }, null, ambilToken);
    const { id, ukuran_potongan: ukuranPotongan, jumlah_potongan: jumlahPotongan } = mulai;

    for (let indeks = 0; indeks < jumlahPotongan; indeks++) {
        const awal = indeks * ukuranPotongan;
        const potongan = berkas.slice(awal, Math.min(awal + ukuranPotongan, berkas.size));

        for (let percobaan = 1; ; percobaan++) {
            try {
                const badan = new FormData();
                badan.append('potongan', potongan, 'potongan');
                await permintaan(
                    'POST',
                    `${alamat}/${id}/potongan/${indeks}`,
                    badan,
                    (terkirim) => saatProgres(Math.round(((awal + terkirim) / berkas.size) * 100)),
                    ambilToken,
                );
                break;
            } catch (galat) {
                if (galat.status !== 0 || percobaan >= PERCOBAAN_POTONGAN) {
                    throw galat;
                }

                await tunggu(1000 * 2 ** percobaan);
            }
        }
    }

    return permintaan('POST', `${alamat}/${id}/selesai`, null, null, ambilToken);
}

function ukuranTerbaca(byte) {
    return byte >= MB ? `${(byte / MB).toFixed(1)} MB` : `${Math.max(1, Math.round(byte / 1024))} KB`;
}

export default function UnggahLampiran({ kanal, aturan, onChange, galat, ambilToken = null }) {
    const alamat = kanal === 'wbs' ? '/wbs/lampiran/unggah' : '/lampiran/unggah';
    const format = FORMAT[kanal] ?? FORMAT.pengaduan;
    const [daftar, setDaftar] = useState([]);
    const masukan = useRef(null);
    const onChangeTerbaru = useRef(onChange);
    onChangeTerbaru.current = onChange;

    useEffect(() => {
        onChangeTerbaru.current(
            daftar.filter((item) => item.status === 'siap').map((item) => item.id),
            daftar.some((item) => item.status === 'mengunggah'),
        );
    }, [daftar]);

    useEffect(() => () => daftar.forEach((item) => item.pratinjau && URL.revokeObjectURL(item.pratinjau)), []);

    function ubah(kunci, perubahan) {
        setDaftar((sebelum) => sebelum.map((item) => (item.kunci === kunci ? { ...item, ...perubahan } : item)));
    }

    async function pilih(event) {
        const berkasDipilih = Array.from(event.target.files ?? []);
        event.target.value = '';

        const sisaSlot = aturan.maks_berkas - daftar.length;
        const totalSaatIni = daftar.reduce((jumlah, item) => jumlah + item.ukuran, 0);
        let totalBaru = totalSaatIni;
        const baru = [];

        for (const berkas of berkasDipilih) {
            const kunci = `${berkas.name}-${berkas.size}-${Math.random()}`;
            const item = {
                kunci,
                nama: berkas.name,
                ukuran: berkas.size,
                progres: 0,
                status: 'mengunggah',
                pesan: null,
                pratinjau: /^(image\/(jpeg|png)|video\/)/.test(berkas.type) ? URL.createObjectURL(berkas) : null,
                video: berkas.type.startsWith('video/'),
                berkas,
            };

            if (baru.length >= sisaSlot) {
                item.status = 'gagal';
                item.pesan = `Lampiran paling banyak ${aturan.maks_berkas} berkas.`;
            } else if (totalBaru + berkas.size > aturan.maks_total_mb * MB) {
                item.status = 'gagal';
                item.pesan = `Total ukuran lampiran paling besar ${aturan.maks_total_mb} MB.`;
            } else {
                totalBaru += berkas.size;
            }

            baru.push(item);
        }

        setDaftar((sebelum) => [...sebelum, ...baru]);

        for (const item of baru.filter((calon) => calon.status === 'mengunggah')) {
            try {
                const hasil = await unggahBerkas(item.berkas, kanal, alamat, ambilToken, (progres) => ubah(item.kunci, { progres }));
                ubah(item.kunci, { status: 'siap', progres: 100, id: hasil.id, berkas: null });
            } catch (kegagalan) {
                ubah(item.kunci, { status: 'gagal', pesan: kegagalan.pesan, berkas: null });
            }
        }
    }

    function hapus(item) {
        if (item.id) {
            permintaan('DELETE', `${alamat}/${item.id}`, null, null, ambilToken).catch(() => {});
        }

        if (item.pratinjau) {
            URL.revokeObjectURL(item.pratinjau);
        }

        setDaftar((sebelum) => sebelum.filter((calon) => calon.kunci !== item.kunci));
    }

    const sedangMengunggah = daftar.some((item) => item.status === 'mengunggah');
    const penuh = daftar.filter((item) => item.status !== 'gagal').length >= aturan.maks_berkas;

    return (
        <div className="flex flex-col gap-3">
            <p className="text-sm text-slate-600">
                Paling banyak {aturan.maks_berkas} berkas, total {aturan.maks_total_mb} MB.{' '}
                {aturan.jenis.map((jenis) => `${jenis.label} maks. ${jenis.maks_mb} MB`).join(', ')}. Format: {format.label}.
            </p>

            <input
                ref={masukan}
                id="lampiran"
                type="file"
                multiple
                accept={format.terima}
                onChange={pilih}
                disabled={penuh}
                className="sr-only"
            />
            <button
                type="button"
                onClick={() => masukan.current?.click()}
                disabled={penuh}
                className="self-start inline-flex min-h-11 items-center justify-center rounded-xl border-[1.5px] border-slate-300 bg-white px-4 py-2.5 font-bold text-slate-800 hover:bg-slate-100 disabled:opacity-50"
            >
                Pilih berkas
            </button>

            {daftar.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {daftar.map((item) => (
                        <li key={item.kunci} className="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3">
                            {item.pratinjau &&
                                (item.video ? (
                                    <video src={item.pratinjau} muted className="size-12 shrink-0 rounded object-cover" />
                                ) : (
                                    <img src={item.pratinjau} alt="" className="size-12 shrink-0 rounded object-cover" />
                                ))}
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <span className="truncate text-sm font-medium text-slate-900">{item.nama}</span>
                                <span className="text-xs text-slate-600">{ukuranTerbaca(item.ukuran)}</span>
                                {item.status === 'mengunggah' && (
                                    <div
                                        className="h-1.5 w-full overflow-hidden rounded-full bg-slate-200"
                                        role="progressbar"
                                        aria-valuenow={item.progres}
                                        aria-valuemin={0}
                                        aria-valuemax={100}
                                        aria-label={`Mengunggah ${item.nama}`}
                                    >
                                        <div className="h-full bg-pengaduan-600 transition-all" style={{ width: `${item.progres}%` }} />
                                    </div>
                                )}
                                {item.status === 'siap' && <span className="text-xs text-pengaduan-700">Siap dikirim</span>}
                                {item.status === 'gagal' && <span className="text-xs text-rose-700">{item.pesan}</span>}
                            </div>
                            <button
                                type="button"
                                onClick={() => hapus(item)}
                                disabled={item.status === 'mengunggah'}
                                className="shrink-0 inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 disabled:opacity-40"
                                aria-label={`Hapus ${item.nama}`}
                            >
                                Hapus
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            {sedangMengunggah && <p className="text-sm text-slate-600">Tunggu sampai semua lampiran selesai diunggah.</p>}
            {galat && <p className="text-sm text-rose-700">{galat}</p>}
        </div>
    );
}
