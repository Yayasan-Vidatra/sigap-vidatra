#!/usr/bin/env bash
# SIGAP Vidatra, uji pemulihan (NF-04). Dipasang ke /usr/local/sbin/sigap-uji-pemulihan; cron mingguan, atau
# jalankan manual: sudo sigap-uji-pemulihan
# Memulihkan cadangan terbaru ke basis data sementara sigap_uji_pulih dan folder sementara, membandingkan jumlah
# baris dan berkas dengan data produksi, memeriksa sebagian data repositori, lalu membersihkan semuanya.
# Tidak menyentuh basis data atau berkas produksi, dan tidak membutuhkan kunci aplikasi.
set -euo pipefail

# shellcheck source=/dev/null
source /etc/sigap/cadangan.env
export RESTIC_REPOSITORY RESTIC_PASSWORD_FILE AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY

DB_UJI=sigap_uji_pulih
SEMENTARA="$(mktemp -d /var/tmp/sigap-uji-pulih.XXXXXX)"
mysql_() { mysql --defaults-extra-file="$MYSQL_CNF" "$@"; }

bersihkan() {
    mysql_ -e "DROP DATABASE IF EXISTS \`$DB_UJI\`" || true
    rm -rf "$SEMENTARA"
}
peringatan() {
    local pesan="Uji pemulihan cadangan SIGAP GAGAL di $(hostname) pada $(date -Is): $1"
    sudo -u www-data php "$APP_DIR/artisan" sigap:peringatan-pemantauan "$pesan" >/dev/null 2>&1 \
        || logger -t sigap-uji-pemulihan "$pesan"
}
trap bersihkan EXIT
trap 'peringatan "baris $LINENO, perintah: $BASH_COMMAND"' ERR

# 1. Basis data: pulihkan dump terbaru ke DB sementara. DEFINER dibuang agar trigger bisa dibuat akun cadangan.
mysql_ -e "DROP DATABASE IF EXISTS \`$DB_UJI\`; CREATE DATABASE \`$DB_UJI\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
restic dump --host sigap --tag sigap-db latest sigap-db.sql \
    | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' \
    | mysql_ "$DB_UJI"

gagal=0
for tabel in laporan identitas_pelapor lampiran log_audit users; do
    pulih=$(mysql_ -N -e "SELECT COUNT(*) FROM \`$DB_UJI\`.\`$tabel\`")
    produksi=$(mysql_ -N -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`$tabel\`")
    echo "$tabel: cadangan=$pulih produksi=$produksi"
    # Cadangan paling lama sehari; produksi boleh lebih banyak, tidak boleh lebih sedikit dari separuhnya.
    if (( pulih == 0 && produksi > 0 )) || (( pulih * 2 < produksi )); then gagal=1; fi
done

# Trigger hanya-tambah ikut pulih.
trigger=$(mysql_ -N -e "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$DB_UJI'")
echo "trigger: $trigger"
(( trigger >= 4 )) || gagal=1

# 2. Berkas: pulihkan snapshot terbaru dan bandingkan jumlah berkas.
restic restore --host sigap --tag sigap-berkas latest --target "$SEMENTARA/berkas" >/dev/null
berkas_pulih=$(find "$SEMENTARA/berkas" -type f | wc -l)
berkas_produksi=$(find "$APP_DIR/storage/app" -type f -not -path '*/private/unggahan/*' -not -path '*/livewire-tmp/*' | wc -l)
echo "berkas: cadangan=$berkas_pulih produksi=$berkas_produksi"
(( berkas_pulih * 2 >= berkas_produksi )) || gagal=1

# 3. Baca ulang 10% data repositori untuk memastikan isinya bisa didekripsi.
restic check --read-data-subset=10%

if (( gagal )); then
    peringatan "jumlah data hasil pemulihan tidak wajar (lihat keluaran di atas)."
    exit 1
fi

echo "Uji pemulihan berhasil."
logger -t sigap-uji-pemulihan "Uji pemulihan berhasil."
