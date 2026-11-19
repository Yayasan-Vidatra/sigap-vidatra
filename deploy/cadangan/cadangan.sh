#!/usr/bin/env bash
# SIGAP Vidatra, cadangan harian (NF-04, TOR Bagian 6). Dipasang ke /usr/local/sbin/sigap-cadangan, dijalankan cron.
# - Basis data (mysqldump konsisten) dan berkas privat storage/app dikirim ke repositori restic di luar VPS,
#   terenkripsi di sisi VPS sebelum dikirim.
# - .env, APP_KEY, dan LAMPIRAN_KUNCI_ENKRIPSI TIDAK ikut dicadangkan (Rancangan r1 Bagian 9: kunci induk tidak
#   disimpan di basis data maupun cadangannya). Isi WBS dan identitas pelapor di cadangan tetap terenkripsi; untuk
#   memulihkan, kunci diminta dari pemegang kendali ganda.
# - Cadangan lebih lama dari SIMPAN_HARI dihapus, lalu repositori diperiksa. Kegagalan dikirim ke pengelola TI.
set -euo pipefail

# shellcheck source=/dev/null
source /etc/sigap/cadangan.env
export RESTIC_REPOSITORY RESTIC_PASSWORD_FILE AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY

peringatan() {
    local pesan="Cadangan SIGAP gagal di $(hostname) pada $(date -Is): $1"
    sudo -u www-data php "$APP_DIR/artisan" sigap:peringatan-pemantauan "$pesan" >/dev/null 2>&1 \
        || logger -t sigap-cadangan "$pesan"
}
trap 'peringatan "baris $LINENO, perintah: $BASH_COMMAND"' ERR

restic cat config >/dev/null 2>&1 || restic init

# 1. Basis data. --single-transaction tanpa mengunci tabel; trigger log_audit dan riwayat_pengaturan ikut.
mysqldump --defaults-extra-file="$MYSQL_CNF" --single-transaction --quick --routines --triggers --events \
    --no-tablespaces "$DB_NAME" \
    | restic backup --stdin --stdin-filename sigap-db.sql --tag sigap-db --host sigap

# 2. Berkas privat (lampiran, bukti penyelesaian, berkas jaminan). Unggahan setengah jadi tidak ikut.
restic backup "$APP_DIR/storage/app" --tag sigap-berkas --host sigap \
    --exclude "$APP_DIR/storage/app/private/unggahan" --exclude "$APP_DIR/storage/app/private/livewire-tmp"

# 3. Retensi dan pemeriksaan integritas.
restic forget --host sigap --group-by tags --keep-within "${SIMPAN_HARI:-35}d" --prune
restic check

logger -t sigap-cadangan "Cadangan harian selesai."
