/*
 * File viewer sederhana untuk dokumen PDF dari situs ini sendiri (H-03). Memakai penampil PDF bawaan browser
 * di dalam iframe, tanpa pustaka atau aset pihak ketiga. Browser HP yang tidak menampilkan PDF di dalam
 * halaman tetap bisa membuka dokumen lewat tautan di bawahnya.
 */
export default function PenampilBerkas({ src, judul }) {
    return (
        <div className="flex flex-col gap-2">
            <iframe src={src} title={judul} className="h-[70vh] min-h-96 w-full rounded-lg border border-slate-200 bg-white" />
            <a href={src} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center self-start text-sm font-semibold text-wbs-700 underline underline-offset-2">
                Buka dokumen di tab baru
            </a>
        </div>
    );
}
