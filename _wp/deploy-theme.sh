#!/bin/bash
# Установка темы «Дом мёда» на тестовый сайт: тема, категории, страницы, главная, адреса.
# Запускается на сервере от root. Пароли базы берутся из .env и на экран не выводятся.
set -e
cd /var/docker/dom-meda-new
. ./.env
BASE=https://raw.githubusercontent.com/nikita0071007/promo/main/_wp

rm -rf wp/wp-content/themes/dom-meda-theme
curl -sfL "$BASE/dom-meda-theme.tar.gz" | tar xz -C wp/wp-content/themes
chown -R 33:33 wp/wp-content/themes/dom-meda-theme

W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}

# Категории как в макете
mk() { W term create product_cat "$2" --slug="$1" >/dev/null 2>&1 || true; }
mk k-med "Мёд"; mk k-tea "Травы и чаи"; mk k-perga "Перга и пчелопродукты"; mk k-gift "Горные дары Алтая"
setcat() { s=$1; shift; for id in "$@"; do W post term set "$id" product_cat "$s" >/dev/null; done; }
setcat k-med 3482 3485 3487 3489 3491 3493 3495 3501 3503 3505 3507
setcat k-tea 3512 3514 3516 3518 3520 3522 3524 3695 3696 3697
setcat k-perga 3687 3681 3689 3499
setcat k-gift 3698

# Страницы (оформление берётся из файлов темы page-<адрес>.php)
mkpage() {
  id=$(W post list --post_type=page --post_status=any --name="$1" --field=ID | head -1)
  if [ -z "$id" ]; then id=$(W post create --post_type=page --post_status=publish --post_title="$2" --post_name="$1" --porcelain); fi
  echo "$id"
}
for p in "oplata:Оплата" "dostavka:Доставка" "dokumenty:Документы" "otzyvy:Отзывы" "kontakty:Контакты" "oferta:Оферта" "politika:Политика конфиденциальности"; do
  mkpage "${p%%:*}" "${p#*:}" >/dev/null
done
HOME_ID=$(mkpage glavnaya "Главная")
W option update show_on_front page
W option update page_on_front "$HOME_ID"

W theme activate dom-meda-theme
W rewrite structure '/%postname%/' --hard
W rewrite flush --hard
echo THEME_DONE
