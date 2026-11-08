#!/bin/bash
# Обновление карточек: название, краткое и полное описание, фото (архивы Дом мёда, 8.11.2026)
set -e
cd /var/docker/dom-meda-new
. ./.env
D=/var/docker/dom-meda-new/wp/wp-content/dmcards3
rm -rf $D; mkdir -p $D
curl -sfL "https://raw.githubusercontent.com/nikita0071007/promo/main/_wp/cards3.tar.gz" | tar xz -C $D --strip-components=1
chown -R 33:33 $D
C=/var/www/html/wp-content/dmcards3
W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}
for t in $D/*.title; do
  ID=$(basename $t .title)
  TITLE=$(cat $t)
  W post update $ID --post_title="$TITLE" --post_excerpt="$(cat $D/$ID.excerpt)" --post_content="$(cat $D/$ID.content)" >/dev/null
  if [ -f $D/$ID.jpg ]; then
    AID=$(W media import $C/$ID.jpg --post_id=$ID --title="$TITLE" --porcelain)
    W post meta update $ID _thumbnail_id $AID >/dev/null
    echo "OK $ID $TITLE (+фото)"
  else
    echo "OK $ID $TITLE (без фото)"
  fi
done
rm -rf $D
echo CARDS_DONE
