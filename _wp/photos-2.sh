#!/bin/bash
# Фото для карточек: Марьин корень и Кедровая живица
set -e
cd /var/docker/dom-meda-new
. ./.env
BASE=https://raw.githubusercontent.com/nikita0071007/promo/main/_wp/photos
D=/var/docker/dom-meda-new/wp/wp-content/dmph
mkdir -p $D
for f in marin-koren zhivitsa-kedrovaya; do curl -sfL "$BASE/$f.jpg" -o $D/$f.jpg; done
chown -R 33:33 $D
W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}
setph() { # часть названия, файл
  PID=$(W post list --post_type=product --post_status=publish,private --s="$1" --field=ID | head -1)
  [ -z "$PID" ] && { echo "НЕ НАЙДЕН товар: $1"; return; }
  AID=$(W media import /var/www/html/wp-content/dmph/$2.jpg --post_id=$PID --title="$1" --porcelain)
  W post meta update $PID _thumbnail_id $AID >/dev/null
  echo "OK $1 → товар $PID, фото $AID"
}
setph "Марьин корень" marin-koren
setph "Живица" zhivitsa-kedrovaya
rm -rf $D
echo PHOTOS_DONE
