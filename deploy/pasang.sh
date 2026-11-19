#!/usr/bin/env bash
# SIGAP Vidatra, pemasangan konfigurasi server (Ubuntu LTS, Hari 14). Jalankan sebagai root dari folder aplikasi:
#   sudo bash deploy/pasang.sh
# Prasyarat (Hari 1): nginx, php8.3-fpm (+ ekstensi Laravel), mysql/mariadb, supervisor, certbot, restic, ufw,
# ClamAV, ExifTool, FFmpeg; aplikasi di /var/www/sigap dengan .env server. Skrip ini tidak membuat atau mengubah .env.
# Aman dijalankan ulang.
set -euo pipefail

APP_DIR=/var/www/sigap
DOMAIN=sigap.vidatra.sch.id
SUMBER="$(cd "$(dirname "$0")" && pwd)"

[[ $EUID -eq 0 ]] || { echo "Jalankan sebagai root." >&2; exit 1; }

# 1. Firewall: hanya 80, 443, dan SSH (TOR Bagian 6). Login SSH dengan kunci diatur di sshd_config.
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
grep -qE '^\s*PasswordAuthentication\s+no' /etc/ssh/sshd_config \
    || echo "PERINGATAN: set 'PasswordAuthentication no' di /etc/ssh/sshd_config (SSH hanya dengan kunci)." >&2

# 2. PHP-FPM: pool sigap tanpa access.log. Pool bawaan "www" dimatikan agar tidak ada log akses FPM lain.
install -m 644 "$SUMBER/php-fpm/sigap.conf" /etc/php/8.3/fpm/pool.d/sigap.conf
[[ -f /etc/php/8.3/fpm/pool.d/www.conf ]] && mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.nonaktif
install -d -o www-data -g www-data -m 750 /var/log/php
systemctl restart php8.3-fpm

# 3. Nginx: log tanpa IP untuk semua jalur. Log akses tingkat http bawaan (format combined berisi IP) dimatikan.
install -m 644 "$SUMBER/nginx/fastcgi-sigap.conf" /etc/nginx/snippets/fastcgi-sigap.conf
install -m 644 "$SUMBER/nginx/sigap.conf" /etc/nginx/sites-available/sigap
ln -sf /etc/nginx/sites-available/sigap /etc/nginx/sites-enabled/sigap
rm -f /etc/nginx/sites-enabled/default
sed -i -E 's#^(\s*)access_log\s+/var/log/nginx/access\.log.*#\1access_log off;#' /etc/nginx/nginx.conf
install -d -m 755 /var/www/letsencrypt

# 4. Sertifikat Let's Encrypt (sekali). Blok 443 butuh sertifikat, jadi sertifikat pertama diambil dengan mode
#    standalone; kait pre/post tersimpan sehingga perpanjangan otomatis (timer certbot) menghentikan dan
#    menyalakan Nginx sendiri.
if [[ ! -f /etc/letsencrypt/live/$DOMAIN/fullchain.pem ]]; then
    certbot certonly --standalone -d "$DOMAIN" --agree-tos --no-eff-email --register-unsafely-without-email -n \
        --pre-hook "systemctl stop nginx" --post-hook "systemctl start nginx"
fi
nginx -t
systemctl enable --now nginx
systemctl reload nginx

# 5. Rotasi log: log Nginx SIGAP (tanpa IP) disimpan 14 hari.
cat > /etc/logrotate.d/sigap <<'ROT'
/var/log/nginx/sigap-*.log /var/log/php/sigap-*.log {
    daily
    rotate 14
    missingok
    notifempty
    compress
    delaycompress
    sharedscripts
    postrotate
        [ -s /run/nginx.pid ] && kill -USR1 "$(cat /run/nginx.pid)"
    endscript
}
ROT

# 6. Worker antrean (supervisor) dan cron scheduler, pemantauan, cadangan.
install -m 644 "$SUMBER/supervisor/sigap-antrean.conf" /etc/supervisor/conf.d/sigap-antrean.conf
supervisorctl reread
supervisorctl update
install -m 755 "$SUMBER/pantau/cek-kesehatan.sh" /usr/local/sbin/sigap-cek-kesehatan
install -m 755 "$SUMBER/cadangan/cadangan.sh" /usr/local/sbin/sigap-cadangan
install -m 755 "$SUMBER/cadangan/uji-pemulihan.sh" /usr/local/sbin/sigap-uji-pemulihan
install -m 644 "$SUMBER/cron/sigap" /etc/cron.d/sigap

# 7. Berkas konfigurasi rahasia (diisi manual, root saja). Contoh ada di deploy/cadangan/cadangan.env.example.
install -d -m 700 /etc/sigap
[[ -f /etc/sigap/cadangan.env ]] || install -m 600 "$SUMBER/cadangan/cadangan.env.example" /etc/sigap/cadangan.env

# 8. Laravel: cache konfigurasi dan izin folder.
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
sudo -u www-data php "$APP_DIR/artisan" optimize

echo "Selesai. Isi /etc/sigap/cadangan.env dan SIGAP_SUREL_PENGELOLA_TI di .env, lalu jalankan: sigap-cadangan && sigap-uji-pemulihan"
