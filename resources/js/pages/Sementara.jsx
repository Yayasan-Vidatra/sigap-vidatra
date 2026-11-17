import { Head, Link } from '@inertiajs/react';
import PublicLayout from '../layouts/PublicLayout';

export default function Sementara({ judul, keterangan }) {
    return (
        <PublicLayout>
            <Head title={judul} />
            <section className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-5">
                <h1 className="text-xl font-bold sm:text-2xl">{judul}</h1>
                <p className="text-slate-600">{keterangan}</p>
                <Link href="/" className="font-medium text-teal-700 hover:underline">
                    Kembali ke Beranda
                </Link>
            </section>
        </PublicLayout>
    );
}
