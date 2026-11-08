#!/bin/bash
set -e
cd /var/docker/dom-meda-new
. ./.env
D=/var/docker/dom-meda-new/wp/wp-content/dmph
mkdir -p $D
curl -sfL "https://raw.githubusercontent.com/nikita0071007/promo/main/_wp/photos/maralij-koren.jpg" -o $D/maralij-koren.jpg
chown -R 33:33 $D
W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}
M=$(W post list --post_type=product --post_status=publish,private,draft --s="Маралий" --field=ID | head -1)
if [ -n "$M" ]; then
  AID=$(W media import /var/www/html/wp-content/dmph/maralij-koren.jpg --post_id=$M --title="Маралий корень 100 г" --porcelain)
  W post meta update $M _thumbnail_id $AID >/dev/null
  echo "OK Маралий корень → товар $M"
else echo "НЕ НАЙДЕН Маралий"; fi
rm -rf $D
echo MARAL_DONE
