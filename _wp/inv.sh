#!/bin/bash
cd /var/docker/dom-meda-new
. ./.env
docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
  -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
  -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli \
  wp post list --post_type=product --post_status=any --orderby=ID --order=ASC --posts_per_page=100 \
  --fields=ID,post_title,post_status --format=csv | sed 's/"//g'
