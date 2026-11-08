#!/bin/bash
# Лист бадана 100 г (бывш. «Корень бадана») + Холстик прополисный 250 ₽ в каталог
set -e
cd /var/docker/dom-meda-new
. ./.env
BASE=https://raw.githubusercontent.com/nikita0071007/promo/main/_wp/photos
D=/var/docker/dom-meda-new/wp/wp-content/dmph
mkdir -p $D
curl -sfL "$BASE/badan-list.jpg" -o $D/badan-list.jpg
chown -R 33:33 $D
W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}
# --- Лист бадана ---
B=$(W post list --post_type=product --post_status=publish,private,draft --s="бадан" --field=ID | head -1)
if [ -n "$B" ]; then
  W post update $B --post_title="Лист бадана, 100 г" --post_name="list-badana-100g" \
    --post_excerpt="Листья бадана толстолистного сушёные, упаковка 100 г." \
    --post_content="Листья бадана толстолистного сушёные. Упаковка 100 г. Срок годности и условия хранения указаны на упаковке." >/dev/null
  AID=$(W media import $D/badan-list.jpg --post_id=$B --title="Лист бадана 100 г" --porcelain)
  W post meta update $B _thumbnail_id $AID >/dev/null
  echo "OK Лист бадана → товар $B (цена прежняя: $(W post meta get $B _price))"
else echo "НЕ НАЙДЕН бадан"; fi
# --- Холстик ---
H=$(W post list --post_type=product --post_status=publish,private,draft --s="Холстик" --field=ID | head -1)
if [ -n "$H" ]; then
  W post update $H --post_status=publish --post_title="Холстик прополисный" >/dev/null
  W post meta update $H _regular_price 250 >/dev/null
  W post meta update $H _price 250 >/dev/null
  W post meta update $H _dm_model "" >/dev/null
  W post meta update $H _stock_status instock >/dev/null
  W post meta update $H _visibility visible >/dev/null
  W post term set $H product_cat k-perga >/dev/null
  W post term set $H product_visibility --by=slug "" >/dev/null 2>&1 || true
  echo "OK Холстик → товар $H, 250 ₽, категория: $(W post term list $H product_cat --field=name)"
else echo "НЕ НАЙДЕН Холстик"; fi
rm -rf $D
W wc tool run clear_transients --user=1 >/dev/null 2>&1 || true
echo CARDS3_DONE
