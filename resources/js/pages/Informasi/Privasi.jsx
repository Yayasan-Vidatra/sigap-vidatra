import { Head } from '@inertiajs/react';
import TeksBerparagraf from '../../components/TeksBerparagraf';
import PublicLayout from '../../layouts/PublicLayout';

export default function Privasi({ bagian }) {
    return (
        <PublicLayout lebar="sedang">
            <Head title="Kebijakan Privasi" />

            <article className="flex flex-col gap-6">
                <h1 className="text-xl font-bold sm:text-2xl">Kebijakan Privasi</h1>

                {bagian.length === 0 ? (
                    <p className="rounded-2xl border border-garis bg-white p-5 sm:p-7 text-slate-600">
                        Kebijakan privasi sedang disiapkan.
                    </p>
                ) : (
                    bagian.map((butir, i) => (
                        <section key={i} className="flex flex-col gap-2 rounded-2xl border border-garis bg-white p-5 sm:p-7">
                            <h2 className="text-lg font-extrabold">{butir.judul}</h2>
                            <TeksBerparagraf teks={butir.isi} />
                        </section>
                    ))
                )}
            </article>
        </PublicLayout>
    );
}
