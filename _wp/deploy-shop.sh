#!/bin/bash
# Развёртывание плагина «Дом мёда — магазин» на тестовый сайт и настройка товаров.
# Запускается на сервере от root. Пароли базы берутся из .env и на экран не выводятся.
set -e
cd /var/docker/dom-meda-new
. ./.env
BASE=https://raw.githubusercontent.com/nikita0071007/promo/main/_wp

curl -sfL "$BASE/dom-meda-shop.tar.gz" | tar xz -C wp/wp-content/plugins
chown -R 33:33 wp/wp-content/plugins/dom-meda-shop

W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}

W plugin activate dom-meda-shop

# Магазин: рубли, одна страна, без налогов
W option update woocommerce_currency RUB
W option update woocommerce_currency_pos right_space
W option update woocommerce_price_thousand_sep ' '
W option update woocommerce_price_num_decimals 0
W option update woocommerce_default_country RU
W option update woocommerce_allowed_countries specific
W option update woocommerce_specific_allowed_countries '["RU"]' --format=json
W option update woocommerce_calc_taxes no
W option update woocommerce_enable_guest_checkout yes
W option update woocommerce_dm_link_settings '{"enabled":"yes"}' --format=json

# Корзина и оформление заказа: страницы с шорткодами (классический вид, в нём работают наши поля)
CART=$(W post list --post_type=page --post_status=publish --s='woocommerce_cart' --field=ID | head -1)
CHK=$(W post list --post_type=page --post_status=publish --s='woocommerce_checkout' --field=ID | head -1)
[ -n "$CART" ] && W option update woocommerce_cart_page_id "$CART"
[ -n "$CHK" ] && W option update woocommerce_checkout_page_id "$CHK"
echo "Корзина: $CART, Оформление: $CHK"

# Модели цены по товарам
set_model() { # id модель цена_за_кг
  W post meta update "$1" _dm_model "$2" >/dev/null
  W post meta update "$1" _dm_kg "${3:-0}" >/dev/null
}
for id in 3482 3485 3487 3489 3491 3493 3495; do set_model $id jar 800; done      # мёд
for id in 3501 3503 3505 3507; do set_model $id jar 850; done                    # медовые композиции
set_model 3499 comb 4000                                                        # мёд в сотах
for id in 3512 3514 3516 3518 3520 3522 3524; do set_model $id tea 0; done      # травяные чаи
set_model 3687 perga 4500                                                       # перга
set_model 3681 perga 4000                                                       # пыльца
set_model 3689 propolis 8000                                                    # прополис
echo DONE
