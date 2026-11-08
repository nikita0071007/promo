#!/bin/bash
# Обновление темы и починка данных после переноса. Запускается на сервере от root.
set -e
cd /var/docker/dom-meda-new
. ./.env
BASE=https://raw.githubusercontent.com/nikita0071007/promo/main/_wp

rm -rf wp/wp-content/themes/dom-meda-theme
curl -sfL "$BASE/dom-meda-theme.tar.gz" | tar xz -C wp/wp-content/themes
chown -R 33:33 wp/wp-content/themes/dom-meda-theme
echo "1. Тема обновлена"

W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}

# Остатки старого конструктора WPBakery ([vc_row] и т.п.) в содержимом страниц и записей
W search-replace '\[/?vc_[^\]]*\]' '' dmc_posts --regex --regex-delimiter='#' --include-columns=post_content
echo "2. Остатки старого конструктора убраны"

# Старые отзывы покупателей: в новой версии WooCommerce у них должен быть тип review
docker exec dm_new_db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" wp -e "UPDATE dmc_comments c JOIN dmc_posts p ON p.ID=c.comment_post_ID SET c.comment_type=\"review\" WHERE p.post_type=\"product\" AND c.comment_type IN (\"\",\"comment\");"'
echo "3. Отзывы переведены в формат магазина"

# Уменьшенные копии фото под новые размеры
W media regenerate --yes
echo "4. Фото пересжаты"

echo "--- Версии ---"
W core version
W plugin list --fields=name,status,update,version
echo FIX1_DONE
