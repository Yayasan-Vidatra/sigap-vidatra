import { Link } from '@inertiajs/react';

/*
 * Tata letak dan komponen bersama halaman publik H-01 sampai H-08 (mockup prd/mockup, disetujui): header, footer,
 * tombol, kartu, kolom isian, dan bagian formulir. Biru #1E3A9E (pengaduan-700) untuk Pengaduan, biru tua #0B1B4D
 * (wbs-700) untuk WBS, latar #F5F7FC. Semua aset (logo, huruf) dari situs sendiri; tidak ada domain lain, sehingga
 * aman dipakai di halaman /wbs*.
 */

const LEBAR = {
    penuh: 'max-w-[1200px]',
    sedang: 'max-w-4xl',
    sempit: 'max-w-lg',
};

export const gayaIsian =
    'w-full min-h-12 rounded-xl border-[1.5px] border-slate-300 bg-white px-3.5 py-3 text-base text-slate-900 placeholder:text-slate-500 focus:border-pengaduan-700 focus:outline-none focus:ring-2 focus:ring-pengaduan-700/25 aria-invalid:border-rose-600';

const VARIAN_TOMBOL = {
    utama: 'bg-pengaduan-700 text-white hover:bg-pengaduan-800 focus-visible:outline-pengaduan-700',
    wbs: 'bg-wbs-700 text-white hover:bg-wbs-600 focus-visible:outline-wbs-700',
    garis: 'border-[1.5px] border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:outline-pengaduan-700',
    garisWbs: 'border-[1.5px] border-wbs-700 bg-white text-wbs-700 hover:bg-wbs-50 focus-visible:outline-wbs-700',
    putih: 'bg-white text-wbs-700 hover:bg-wbs-50 focus-visible:outline-white',
    bahaya: 'bg-rose-700 text-white hover:bg-rose-800 focus-visible:outline-rose-700',
};

/**
 * Tombol atau tautan bergaya tombol, tinggi minimal 44 px. href internal memakai Link Inertia; unduhan dan
 * tautan biasa memakai biasa=true.
 */
export function Tombol({ href, biasa = false, varian = 'utama', className = '', children, ...sisa }) {
    const kelas = `inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 py-3 text-center text-base font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 ${VARIAN_TOMBOL[varian]} ${className}`;

    if (href) {
        return biasa ? (
            <a href={href} className={kelas} {...sisa}>
                {children}
            </a>
        ) : (
            <Link href={href} className={kelas} {...sisa}>
                {children}
            </Link>
        );
    }

    return (
        <button type="button" className={kelas} {...sisa}>
            {children}
        </button>
    );
}

export function Kartu({ as: Komponen = 'section', className = '', children, ...sisa }) {
    return (
        <Komponen className={`rounded-2xl border border-garis bg-white p-5 sm:p-7 ${className}`} {...sisa}>
            {children}
        </Komponen>
    );
}

/**
 * Bagian formulir bernomor (fieldset). warna: pengaduan atau wbs.
 */
export function Bagian({ nomor, judul, tambahan, warna = 'pengaduan', className = '', children }) {
    return (
        <fieldset className={`m-0 flex min-w-0 flex-col gap-5 rounded-2xl border border-garis bg-white p-5 sm:p-7 ${className}`}>
            <legend className="float-left flex w-full flex-wrap items-center gap-3 p-0 text-lg font-extrabold sm:text-xl">
                {nomor && (
                    <span
                        aria-hidden="true"
                        className={`inline-flex size-8 items-center justify-center rounded-full text-sm text-white ${warna === 'wbs' ? 'bg-wbs-700' : 'bg-pengaduan-700'}`}
                    >
                        {nomor}
                    </span>
                )}
                {judul}
                {tambahan && <span className="text-sm font-semibold text-slate-600">{tambahan}</span>}
            </legend>
            {children}
        </fieldset>
    );
}

export function Kolom({ id, label, wajib = false, keterangan, galat, children }) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5">
            <label htmlFor={id} className="text-[15px] font-semibold text-slate-900">
                {label}
                {wajib && <span className="text-rose-700"> *</span>}
            </label>
            {keterangan && (
                <p id={`${id}-keterangan`} className="text-sm text-slate-600">
                    {keterangan}
                </p>
            )}
            {children}
            {galat && (
                <p id={`${id}-galat`} className="text-sm font-medium text-rose-700">
                    {galat}
                </p>
            )}
        </div>
    );
}

/**
 * Judul halaman dengan remah roti dan aksi di kanan (opsional).
 */
export function JudulHalaman({ remah, judul, keterangan, children }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div className="flex min-w-0 flex-col gap-1.5">
                {remah && (
                    <p className="text-sm text-slate-600">
                        <Link href="/" className="inline-flex min-h-11 items-center text-slate-600 underline underline-offset-2">
                            Beranda
                        </Link>{' '}
                        / {remah}
                    </p>
                )}
                <h1 className="text-[28px] leading-tight font-extrabold tracking-tight sm:text-[38px]">{judul}</h1>
                {keterangan && <p className="text-base text-slate-600">{keterangan}</p>}
            </div>
            {children}
        </div>
    );
}

export function Logo({ ukuran = 44, putih = false }) {
    return (
        <img
            src="/img/logo-96.png"
            srcSet="/img/logo-96.png 1x, /img/logo-192.png 2x"
            alt={putih ? '' : 'Logo Yayasan Vidatra'}
            width={ukuran}
            height={ukuran}
            className={`shrink-0 object-contain ${putih ? 'rounded-full bg-white p-0.5' : ''}`}
            style={{ width: ukuran, height: ukuran }}
        />
    );
}

const tautanMenu = 'inline-flex min-h-11 items-center text-[15px] font-semibold text-slate-700 hover:text-pengaduan-700';

function Header({ wbs }) {
    return (
        <header className="border-b border-garis bg-white">
            {/* HP: logo dan tombol di baris pertama, menu di baris kedua. Layar lebar: satu baris. */}
            <div className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-x-3 px-4 pt-2 pb-1 sm:gap-x-5 sm:gap-y-2 sm:px-6 sm:py-3">
                <Link href="/" className="flex min-h-11 min-w-0 items-center gap-2.5 text-slate-900 sm:gap-3">
                    <Logo ukuran={40} />
                    <span className="flex min-w-0 flex-col leading-tight">
                        <span className="text-base font-extrabold sm:text-lg">SIGAP Vidatra</span>
                        <span className="text-xs text-slate-600 max-sm:hidden">Yayasan Vidatra Bontang</span>
                    </span>
                </Link>
                {wbs ? (
                    <>
                        <Tombol href="/wbs" varian="wbs" className="px-3.5 py-2.5 text-sm sm:order-3 sm:px-4 sm:text-[15px]">
                            Buat laporan
                        </Tombol>
                        <nav aria-label="Menu utama" className="flex w-full flex-wrap items-center gap-x-5 sm:order-2 sm:ml-auto sm:w-auto">
                            <Link href="/wbs/lacak" className={tautanMenu}>
                                Lacak laporan
                            </Link>
                        </nav>
                    </>
                ) : (
                    <>
                        <Tombol href="/pengaduan" className="px-3.5 py-2.5 text-sm sm:order-3 sm:px-4 sm:text-[15px]">
                            Kirim pengaduan
                        </Tombol>
                        <nav aria-label="Menu utama" className="flex w-full flex-wrap items-center gap-x-5 sm:order-2 sm:ml-auto sm:w-auto">
                            <a href="/#cara" className={tautanMenu}>
                                Cara kerja
                            </a>
                            <Link href="/lacak" className={tautanMenu}>
                                Lacak laporan
                            </Link>
                            <Link href="/faq" className={tautanMenu}>
                                Tanya jawab
                            </Link>
                        </nav>
                    </>
                )}
            </div>
        </header>
    );
}

function Footer({ wbs }) {
    return (
        <footer className="bg-wbs-700 text-wbs-200">
            <div className="mx-auto flex max-w-[1200px] flex-wrap justify-between gap-6 px-4 py-10 text-sm sm:px-6">
                <div className="flex items-center gap-3">
                    <Logo ukuran={40} putih />
                    <div className="leading-relaxed">
                        <div className="font-bold text-white">SIGAP Vidatra</div>
                        Yayasan Vidatra · SD, SMP, SMA YPVDP · Pelaksana Harian
                    </div>
                </div>
                <nav aria-label="Tautan bawah" className="flex flex-wrap items-center gap-x-6 gap-y-1">
                    <Link href={wbs ? '/wbs/lacak' : '/lacak'} className="inline-flex min-h-11 items-center text-white hover:underline">
                        Lacak laporan
                    </Link>
                    <Link href="/faq" className="inline-flex min-h-11 items-center text-white hover:underline">
                        Tanya jawab
                    </Link>
                    <Link href="/privasi" className="inline-flex min-h-11 items-center text-white hover:underline">
                        Kebijakan privasi
                    </Link>
                </nav>
            </div>
        </footer>
    );
}

/**
 * polos: halaman mengatur sendiri lebar dan latar setiap bagian (Beranda). Selain itu isi dibungkus wadah
 * dengan lebar penuh (1200 px), sedang, atau sempit. wbs: menu untuk kanal WBS internal (Lacak WBS di /wbs/lacak).
 */
export default function PublicLayout({ children, polos = false, lebar = 'penuh', wbs = false }) {
    return (
        <div className="flex min-h-screen flex-col overflow-x-clip bg-latar font-sans text-slate-900">
            <a
                href="#isi"
                className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:font-semibold"
            >
                Lewati ke isi
            </a>
            <Header wbs={wbs} />
            {polos ? (
                <main id="isi" className="flex-1">
                    {children}
                </main>
            ) : (
                <main id="isi" className={`mx-auto w-full flex-1 px-4 pt-8 pb-16 sm:px-6 ${LEBAR[lebar] ?? LEBAR.penuh}`}>
                    {children}
                </main>
            )}
            <Footer wbs={wbs} />
        </div>
    );
}
