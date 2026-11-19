/**
 * Menampilkan teks dari Pengaturan sebagai teks biasa (tanpa HTML): paragraf dipisah baris kosong, dan baris yang
 * diawali "- " menjadi butir daftar.
 */
export default function TeksBerparagraf({ teks }) {
    const blok = (teks ?? '')
        .split(/\n\s*\n/)
        .map((bagian) => bagian.trim())
        .filter(Boolean);

    return (
        <div className="flex flex-col gap-3 text-slate-700">
            {blok.map((bagian, i) => {
                const baris = bagian.split('\n').map((b) => b.trim());
                const pembuka = baris.filter((b) => !b.startsWith('- '));
                const butir = baris.filter((b) => b.startsWith('- ')).map((b) => b.slice(2));

                return (
                    <div key={i} className="flex flex-col gap-2">
                        {pembuka.length > 0 && <p>{pembuka.join(' ')}</p>}
                        {butir.length > 0 && (
                            <ul className="list-disc space-y-1 pl-5">
                                {butir.map((isi, j) => (
                                    <li key={j}>{isi}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
