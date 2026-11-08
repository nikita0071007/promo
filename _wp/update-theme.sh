#!/bin/bash
# Быстрое обновление темы + исправление страницы магазина. Запускается на сервере от root.
set -e
cd /var/docker/dom-meda-new
. ./.env
BASE=https://raw.githubusercontent.com/nikita0071007/promo/main/_wp
rm -rf wp/wp-content/themes/dom-meda-theme
curl -sfL "$BASE/dom-meda-theme.tar.gz" | tar xz -C wp/wp-content/themes
chown -R 33:33 wp/wp-content/themes/dom-meda-theme
echo "Тема обновлена"
W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}
SID=$(W post list --post_type=page --post_status=publish --name=shop --field=ID | head -1)
if [ -n "$SID" ]; then W option update woocommerce_shop_page_id "$SID" >/dev/null; echo "Страница магазина: $SID"; fi
echo UPDATE_DONE
