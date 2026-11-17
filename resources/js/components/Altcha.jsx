import 'altcha';
import 'altcha/i18n/id';
import { useEffect, useRef } from 'react';

/*
 * CAPTCHA ALTCHA (proof-of-work) untuk formulir publik: Pengaduan sekarang, WBS (H-04) nanti.
 * Tantangan diambil dari server sendiri (/altcha/tantangan) tanpa sesi atau cookie; tidak ada
 * skrip, logo, atau tautan pihak ketiga. Pengumpulan sinyal interaksi (HIS) dimatikan karena
 * hanya dipakai layanan ALTCHA Sentinel yang tidak kita pakai.
 *
 * onChange menerima payload saat terverifikasi, dan string kosong saat kedaluwarsa atau gagal.
 *
 * Widget menghitung hash lewat Web Crypto (crypto.subtle), yang hanya tersedia di konteks aman
 * (HTTPS atau localhost). Di luar itu widget tidak dijalankan dan pesan penjelas ditampilkan.
 */
const KONFIGURASI = JSON.stringify({
    hideLogo: true,
    hideFooter: true,
    humanInteractionSignature: false,
});

function koneksiAmanTersedia() {
    return typeof window !== 'undefined' && window.isSecureContext === true && Boolean(window.crypto?.subtle);
}

export default function Altcha({ onChange, galat, id = 'altcha' }) {
    const widget = useRef(null);
    const onChangeTerbaru = useRef(onChange);
    onChangeTerbaru.current = onChange;
    const koneksiAman = koneksiAmanTersedia();

    useEffect(() => {
        const elemen = widget.current;

        if (!elemen) {
            return undefined;
        }

        function saatTerverifikasi(event) {
            onChangeTerbaru.current(event.detail?.payload ?? '');
        }

        function saatStatusBerubah(event) {
            if (event.detail?.state !== 'verified') {
                onChangeTerbaru.current('');
            }
        }

        elemen.addEventListener('verified', saatTerverifikasi);
        elemen.addEventListener('statechange', saatStatusBerubah);

        return () => {
            elemen.removeEventListener('verified', saatTerverifikasi);
            elemen.removeEventListener('statechange', saatStatusBerubah);
        };
    }, [koneksiAman]);

    return (
        <div className="flex flex-col gap-1.5">
            {koneksiAman ? (
                <altcha-widget
                    ref={widget}
                    id={id}
                    challenge="/altcha/tantangan"
                    name="altcha"
                    language="id"
                    auto="off"
                    configuration={KONFIGURASI}
                />
            ) : (
                <p id={`${id}-tanpa-https`} role="alert" className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    Verifikasi butuh koneksi aman (HTTPS). Buka situs ini lewat https://
                </p>
            )}
            {galat && (
                <p id={`${id}-galat`} className="text-sm text-rose-700">
                    {galat}
                </p>
            )}
        </div>
    );
}
