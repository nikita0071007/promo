#!/bin/bash
# Автоматические резервные копии сайта «Дом мёда»: база + файлы, каждую ночь в 03:15, хранится 14 дней.
# Запускается на сервере от root. Пароли на экран не выводятся.
set -e
cd /var/docker/dom-meda-new
mkdir -p /var/backups/dom-meda
chmod 700 /var/backups/dom-meda

cat > /usr/local/bin/dm-backup.sh <<'EOS'
#!/bin/bash
set -e
cd /var/docker/dom-meda-new
. ./.env
D=/var/backups/dom-meda
T=$(date +%Y-%m-%d_%H%M)
DB=$(docker ps --format '{{.Names}}' | grep -E '^dmnew.*db' | head -1)
[ -z "$DB" ] && { echo "Контейнер базы не найден"; exit 1; }
docker exec -e MYSQL_PWD="$DB_PASS" "$DB" sh -c 'mariadb-dump -uwp --single-transaction --routines wp 2>/dev/null || mysqldump -uwp --single-transaction --routines wp' | gzip > "$D/$T-db.sql.gz"
tar czf "$D/$T-files.tar.gz" --exclude='wp/wp-content/cache' --exclude='wp/wp-content/upgrade' -C /var/docker/dom-meda-new wp/wp-content docker-compose.yml .env 2>/dev/null || tar czf "$D/$T-files.tar.gz" -C /var/docker/dom-meda-new wp/wp-content
chmod 600 "$D/$T"-*
find "$D" -type f -mtime +14 -delete
# проверка: база не пустая
[ "$(stat -c %s "$D/$T-db.sql.gz")" -gt 2000 ] || { echo "ВНИМАНИЕ: копия базы подозрительно маленькая"; exit 2; }
echo "Копия готова: $T"
EOS
chmod 700 /usr/local/bin/dm-backup.sh

( crontab -l 2>/dev/null | grep -v dm-backup.sh; echo "15 3 * * * /usr/local/bin/dm-backup.sh >> /var/log/dm-backup.log 2>&1" ) | crontab -

/usr/local/bin/dm-backup.sh
echo "--- Что лежит в /var/backups/dom-meda:"
ls -lh /var/backups/dom-meda
df -h /var/backups | tail -1
echo BACKUP_DONE
