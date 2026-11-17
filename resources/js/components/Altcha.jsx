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
 */
const KONFIGURASI = JSON.stringify({
    hideLogo: true,
    hideFooter: true,
    humanInteractionSignature: false,
});

export default function Altcha({ onChange, galat, id = 'altcha' }) {
    const widget = useRef(null);
    const onChangeTerbaru = useRef(onChange);
    onChangeTerbaru.current = onChange;

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
    }, []);

    return (
        <div className="flex flex-col gap-1.5">
            <altcha-widget
                ref={widget}
                id={id}
                challenge="/altcha/tantangan"
                name="altcha"
                language="id"
                auto="off"
                configuration={KONFIGURASI}
            />
            {galat && (
                <p id={`${id}-galat`} className="text-sm text-rose-700">
                    {galat}
                </p>
            )}
        </div>
    );
}
