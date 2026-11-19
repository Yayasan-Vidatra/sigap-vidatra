#!/usr/bin/env bash
# SIGAP Vidatra, pemantauan (NF-04, TOR Bagian 6). Dipasang ke /usr/local/sbin/sigap-cek-kesehatan; cron 5 menit.
# Memeriksa GET /kesehatan (basis data, detak scheduler, detak antrean, disk), proses worker supervisor, layanan,
# dan pemakaian disk. Peringatan dikirim ke SIGAP_SUREL_PENGELOLA_TI paling sering sekali per jam per masalah,
# dan sekali saat pulih. Bila VPS mati total skrip ini ikut mati: pasang juga pemantau uptime luar yang memanggil
# https://sigap.vidatra.sch.id/kesehatan (200 = sehat, 503 = ada yang gagal).
set -uo pipefail

APP_DIR=/var/www/sigap
URL=https://sigap.vidatra.sch.id/kesehatan
STATUS_DIR=/var/lib/sigap-pantau
JEDA_ULANG_DETIK=3600
mkdir -p "$STATUS_DIR"

masalah=()

jawaban=$(curl -sS --max-time 15 -o - -w '\n%{http_code}' "$URL" 2>&1)
kode=$(tail -n1 <<<"$jawaban")
[[ $kode == 200 ]] || masalah+=("/kesehatan menjawab $kode: $(head -n1 <<<"$jawaban" | cut -c1-200)")

for layanan in nginx php8.3-fpm mysql cron supervisor; do
    systemctl is-active --quiet "$layanan" || masalah+=("layanan $layanan tidak berjalan")
done

tidak_jalan=$(supervisorctl status 'sigap-antrean:*' 2>&1 | grep -v RUNNING || true)
[[ -z $tidak_jalan ]] || masalah+=("worker antrean: $tidak_jalan")

pakai=$(df -P "$APP_DIR" | awk 'NR==2 {gsub("%", "", $5); print $5}')
(( pakai < 90 )) || masalah+=("disk terpakai ${pakai}%")

kirim() {
    sudo -u www-data php "$APP_DIR/artisan" sigap:peringatan-pemantauan "$1" >/dev/null 2>&1 \
        || logger -t sigap-pantau "$1"
}

penanda="$STATUS_DIR/bermasalah"
if (( ${#masalah[@]} )); then
    pesan="SIGAP di $(hostname) bermasalah ($(date -Is)):"$'\n'"$(printf -- '- %s\n' "${masalah[@]}")"
    terakhir=$(stat -c %Y "$penanda" 2>/dev/null || echo 0)
    if (( $(date +%s) - terakhir >= JEDA_ULANG_DETIK )); then
        kirim "$pesan"
        touch "$penanda"
    fi
    logger -t sigap-pantau "${masalah[*]}"
    exit 1
fi

if [[ -f $penanda ]]; then
    rm -f "$penanda"
    kirim "SIGAP di $(hostname) kembali normal ($(date -Is))."
fi
