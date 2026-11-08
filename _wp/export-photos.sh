#!/bin/bash
# Выгрузка всех фото товаров в один архив для редактирования
cd /var/docker/dom-meda-new
. ./.env
OUT=/var/docker/dom-meda-new/wp/wp-content/dmexport
rm -rf $OUT; mkdir -p $OUT; chown 33:33 $OUT
docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
  -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
  -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp eval '
$q = get_posts(array("post_type"=>"product","post_status"=>array("publish","private"),"numberposts"=>-1,"orderby"=>"ID","order"=>"ASC"));
foreach ($q as $p) {
  $t = get_post_thumbnail_id($p->ID);
  if (!$t) { echo "БЕЗ ФОТО: {$p->ID} {$p->post_title}\n"; continue; }
  $f = get_attached_file($t);
  if (!$f || !file_exists($f)) { echo "НЕТ ФАЙЛА: {$p->ID}\n"; continue; }
  $name = $p->ID . "-" . sanitize_title(transliterator_transliterate("Any-Latin; Latin-ASCII", $p->post_title)) . "." . pathinfo($f, PATHINFO_EXTENSION);
  copy($f, "/var/www/html/wp-content/dmexport/" . $name);
  echo "OK $name  — {$p->post_title}\n";
}'
cd /var/docker/dom-meda-new/wp/wp-content
(command -v zip >/dev/null && zip -qr /root/dom-meda-photos.zip dmexport) || tar czf /root/dom-meda-photos.tar.gz dmexport
rm -rf $OUT
ls -la /root/dom-meda-photos.* 2>/dev/null
echo EXPORT_DONE
