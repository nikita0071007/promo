#!/bin/bash
# Зизифора, Солодка, Шиповник (черновики), Марьин корень и бадан 100 г, Маралий корень
set -e
cd /var/docker/dom-meda-new
. ./.env
D=/var/docker/dom-meda-new/wp/wp-content/dmcards2
rm -rf $D; mkdir -p $D
curl -sfL "https://raw.githubusercontent.com/nikita0071007/promo/main/_wp/cards2.tar.gz" | tar xz -C $D --strip-components=1
chown -R 33:33 $D
C=/var/www/html/wp-content/dmcards2
W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}
photo() { # id файл название
  AID=$(W media import $C/$2 --post_id=$1 --title="$3" --porcelain)
  W post meta update $1 _thumbnail_id $AID >/dev/null
}
# --- новые товары (черновики, пока нет цен) ---
new() { # название кратко полное файл
  ID=$(W post create --post_type=product --post_status=draft --post_title="$1" --post_excerpt="$2" --post_content="$3" --porcelain)
  W post term set $ID product_cat k-tea >/dev/null
  W post meta update $ID _stock_status instock >/dev/null
  photo $ID $4 "$1"
  echo "OK новый товар $ID $1 (черновик, цена не задана)"
}
new "Зизифора, 50 г" "Зизифора в упаковке 50 г. Для травяных настоев и домашних сборов." "Зизифора в отдельной фасовке. Подойдёт тем, кто предпочитает выбирать травы для своего сбора по отдельности. Способ приготовления смотрите на упаковке.

Фасовка — 50 г." zizifora.jpg
new "Солодка (корень), 50 г" "Корень солодки в упаковке 50 г." "Корень солодки в отдельной фасовке. Через прозрачную часть пакета видны кусочки корня. Способ приготовления и рекомендации производителя указаны на упаковке.

Фасовка — 50 г." solodka.jpg
new "Шиповник (плоды), 100 г" "Плоды шиповника в упаковке 100 г. Для домашнего ягодного настоя." "Плоды шиповника для домашнего настоя. Попробуйте подать готовый напиток тёплым, а к нему поставить мёд или сухофрукты. Способ приготовления смотрите на упаковке.

Фасовка — 100 г." shipovnik.jpg
# --- существующие корни: вес 100 г ---
MR=$(W post list --post_type=product --post_status=any --s="Марьин" --field=ID | head -1)
[ -n "$MR" ] && W post update $MR --post_excerpt="Марьин корень в упаковке 100 г." --post_content="Марьин корень в отдельной фасовке. Кусочки корня видны через прозрачную часть пакета. Сведения о применении и хранении смотрите на этикетке.

Фасовка — 100 г." >/dev/null && echo "OK Марьин корень $MR → 100 г"
BD=$(W post list --post_type=product --post_status=any --s="бадана" --field=ID | head -1)
[ -n "$BD" ] && W post update $BD --post_excerpt="Бадан толстолистный в упаковке 100 г." --post_content="Бадан толстолистный в отдельной фасовке: листья сушёные. Прозрачное окно пакета позволяет рассмотреть содержимое. Способ приготовления смотрите на этикетке.

Фасовка — 100 г." >/dev/null && echo "OK Лист бадана $BD → 100 г"
ML=$(W post list --post_type=product --post_status=any --s="Маралий" --field=ID | head -1)
if [ -n "$ML" ]; then
  W post update $ML --post_excerpt="Маралий корень сушёный в упаковке 100 г." --post_content="Маралий корень сушёный, кусочками. Способ приготовления и рекомендации производителя смотрите на упаковке.

Фасовка — 100 г." >/dev/null
  photo $ML maral.jpg "Маралий корень 100 г"
  echo "OK Маралий корень $ML (+фото)"
fi
rm -rf $D
echo FIN_DONE
