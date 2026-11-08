#!/bin/bash
# Шаг 2: свежая тема, переименование категории, https с настоящим сертификатом Let's Encrypt.
# Запускается на сервере от root. Пароли базы берутся из .env и на экран не выводятся.
set -e
cd /var/docker/dom-meda-new
. ./.env
BASE=https://raw.githubusercontent.com/nikita0071007/promo/main/_wp
HOST=test.dom-meda.com

W() {
  docker run --rm --user 33:33 --network dmnew_default --volumes-from dm_new_wp \
    -e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DB_PASS" \
    -e WORDPRESS_DB_NAME=wp -e WORDPRESS_TABLE_PREFIX=dmc_ wordpress:cli wp "$@"
}

# 1. Тема
rm -rf wp/wp-content/themes/dom-meda-theme
curl -sfL "$BASE/dom-meda-theme.tar.gz" | tar xz -C wp/wp-content/themes
chown -R 33:33 wp/wp-content/themes/dom-meda-theme
echo "1. Тема обновлена"

# 2. Категория «Пыльца и пчелопродукты»
TID=$(W term list product_cat --slug=k-perga --field=term_id | head -1)
[ -n "$TID" ] && W term update product_cat "$TID" --name="Пыльца и пчелопродукты" >/dev/null
echo "2. Категория переименована"

# Режим «магазин скоро откроется» выключаем: сайт и так закрыт паролем test
W option update woocommerce_coming_soon no >/dev/null

# 3. Сертификат: метки Traefik (один раз)
if ! grep -q "dm_new.tls.certresolver" docker-compose.yml; then
  cp docker-compose.yml docker-compose.yml.bak
  cat >> docker-compose.yml <<'EOF'
      - "traefik.http.routers.dm_new.entrypoints=websecure"
      - "traefik.http.routers.dm_new.tls.certresolver=leresolver"
      - "traefik.http.routers.dm_new_http.rule=Host(`test.dom-meda.com`)"
      - "traefik.http.routers.dm_new_http.entrypoints=web"
      - "traefik.http.routers.dm_new_http.middlewares=dm_redirect"
      - "traefik.http.middlewares.dm_redirect.redirectscheme.scheme=https"
EOF
fi
docker-compose -p dmnew up -d
docker network connect dmnew_default traefik_traefik_1 2>/dev/null || true
echo "3. Метки применены, жду сертификат (до 2 минут)"

OK=0
for i in $(seq 1 24); do
  sleep 5
  CODE=$(curl -s -o /dev/null -w "%{http_code}" -m 10 "https://$HOST/" || true)
  if [ "$CODE" = "401" ] || [ "$CODE" = "200" ]; then OK=1; break; fi
done

if [ "$OK" = "1" ]; then
  echo "4. Сертификат выпущен, https работает"
  W search-replace "http://$HOST" "https://$HOST" --all-tables --skip-columns=guid >/dev/null
  W option update home "https://$HOST" >/dev/null
  W option update siteurl "https://$HOST" >/dev/null
  echo "5. Адреса сайта переведены на https"
  echo STEP2_DONE_HTTPS
else
  echo "4. Сертификат не выпустился за 2 минуты. Адреса оставлены на http, сайт работает как раньше."
  docker logs --tail 12 traefik_traefik_1 2>&1 | grep -i -E "acme|certificate|error" | tail -5 | cut -c1-200
  echo STEP2_DONE_HTTP_ONLY
fi
