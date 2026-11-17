import { Link } from '@inertiajs/react';

export default function PublicLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col bg-slate-50 text-slate-900">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-3">
                    <Link href="/" className="text-lg font-semibold text-slate-900">
                        SIGAP Vidatra
                    </Link>
                    <Link
                        href="/lacak"
                        className="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    >
                        Lacak Laporan
                    </Link>
                </div>
            </header>

            <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-6 sm:py-10">{children}</main>

            <footer className="border-t border-slate-200 bg-white">
                <div className="mx-auto flex max-w-4xl flex-col gap-2 px-4 py-5 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                    <span>Yayasan Vidatra</span>
                    <nav className="flex gap-4">
                        <Link href="/faq" className="hover:text-slate-900 hover:underline">
                            FAQ
                        </Link>
                        <Link href="/privasi" className="hover:text-slate-900 hover:underline">
                            Kebijakan Privasi
                        </Link>
                    </nav>
                </div>
            </footer>
        </div>
    );
}
