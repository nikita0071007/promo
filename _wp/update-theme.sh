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
rm -rf wp/wp-content/plugins/dom-meda-shop
curl -sfL "$BASE/dom-meda-shop.tar.gz" | tar xz -C wp/wp-content/plugins
chown -R 33:33 wp/wp-content/plugins/dom-meda-shop
echo "Плагин магазина обновлён"
W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}
W post meta update 3499 _dm_kg 1500 >/dev/null
echo "Мёд в сотах: 1 500 ₽ за кг, рамка от 1,5 кг"
SID=$(W post list --post_type=page --post_status=publish --name=shop --field=ID | head -1)
if [ -n "$SID" ]; then W option update woocommerce_shop_page_id "$SID" >/dev/null; echo "Страница магазина: $SID"; fi
MID=$(W post list --post_type=page --post_status=publish --s='woocommerce_my_account' --field=ID | head -1)
if [ -z "$MID" ]; then MID=$(W post create --post_type=page --post_status=publish --post_title="Мой аккаунт" --post_name=my-account --post_content='[woocommerce_my_account]' --porcelain); fi
W option update woocommerce_myaccount_page_id "$MID" >/dev/null
echo "Личный кабинет: страница $MID"
W rewrite flush >/dev/null 2>&1 || true
echo UPDATE_DONE
