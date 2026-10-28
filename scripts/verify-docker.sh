#!/bin/sh
# Runs a real WonderCMS in Docker, logs in, saves the plugin settings form and
# checks the tag on the home page.
# Usage: ./scripts/verify-docker.sh [wondercms-version]   (default 3.6.0)
set -e
VERSION="${1:-3.6.0}"
cd "$(dirname "$0")/.."
docker run --rm -e VERSION="$VERSION" -v "$PWD":/plugin:ro php:8.3-cli sh -ec '
  apt-get update -qq >/dev/null && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq unzip libzip-dev >/dev/null 2>&1 && docker-php-ext-install -j4 zip >/dev/null 2>&1
  cd /tmp
  curl -fsSL -o wcms.zip "https://github.com/WonderCMS/wondercms/archive/refs/tags/$VERSION.zip"
  unzip -q wcms.zip && mv "wondercms-$VERSION" site
  mkdir -p site/plugins/wondercms-privatus-analytics
  cp /plugin/wondercms-privatus-analytics.php /plugin/version /plugin/summary site/plugins/wondercms-privatus-analytics/
  printf "%s\n" "<?php" "\$p = parse_url(\$_SERVER[\"REQUEST_URI\"], PHP_URL_PATH);" "if (\$p !== \"/\" && is_file(__DIR__ . \$p)) { return false; }" "\$_GET[\"page\"] = ltrim(\$p, \"/\");" "require __DIR__ . \"/index.php\";" > site/router.php
  (cd site && php -S 127.0.0.1:8000 router.php >/tmp/server.log 2>&1 &)
  sleep 1
  B=http://127.0.0.1:8000
  curl -fsS -o /dev/null "$B/"
  php -r "\$f=\"/tmp/site/data/database.js\"; \$d=json_decode(file_get_contents(\$f)); \$d->config->password=password_hash(\"testpass123\", PASSWORD_DEFAULT); file_put_contents(\$f, json_encode(\$d));"

  curl -fsS -o /tmp/plain.html -D /tmp/headers "$B/"
  if grep -q "privatusstats.com" /tmp/plain.html; then echo "FAIL: tag printed with no site id"; exit 1; fi
  echo "ok   no tag before a site id is saved"

  J=/tmp/jar
  curl -sS -o /dev/null -c $J -b $J "$B/loginURL"
  curl -sS -o /dev/null -c $J -b $J -d password=testpass123 "$B/loginURL"
  ADMIN=$(curl -fsS -c $J -b $J "$B/")
  echo "$ADMIN" | grep -q "name=\"privatusAnalyticsSiteId\"" || { echo "FAIL: settings form not shown"; exit 1; }
  echo "$ADMIN" | sed -n "/id=\"menu\"/,/id=\"files\"/p" | grep -q "privatusAnalyticsSiteId" || { echo "FAIL: form is not in the Menu tab"; exit 1; }
  echo "ok   settings form is in the Menu tab"

  T=$(echo "$ADMIN" | grep -o "name=\"token\" value=\"[a-f0-9]*\"" | head -1 | sed "s/.*value=\"\(.*\)\"/\1/")
  curl -fsS -o /dev/null -c $J -b $J --data-urlencode "token=$T" -d privatusAnalyticsSave=1 -d privatusAnalyticsSiteId=pa_Docker123 \
    -d "privatusAnalyticsModules[]=engage" -d "privatusAnalyticsModules[]=vitals" -d privatusAnalyticsSkipLoggedIn=1 "$B/"

  curl -fsS -o /tmp/home.html -D /tmp/headers "$B/"
  EXPECTED="<script defer src=\"https://privatusstats.com/js/pa.js\" data-site=\"pa_Docker123\" data-modules=\"engage,vitals\"></script>"
  grep -qF "$EXPECTED" /tmp/home.html || { echo "FAIL: tag not on home page"; head -40 /tmp/home.html; exit 1; }
  sed -n "/<head>/,/<\/head>/p" /tmp/home.html | grep -qF "privatusstats.com" || { echo "FAIL: tag is not inside head"; exit 1; }
  echo "ok   tag is in the head of the home page for visitors"

  curl -fsS -c $J -b $J -o /tmp/admin.html "$B/"
  if grep -q "src=\"https://privatusstats.com" /tmp/admin.html; then echo "FAIL: logged-in admin was tracked"; exit 1; fi
  echo "ok   logged-in admin gets no tag"

  curl -fsS -o /dev/null -d privatusAnalyticsSave=1 -d token=forged -d privatusAnalyticsSiteId=pa_Forged "$B/"
  grep -q "pa_Forged" /tmp/site/data/database.js && { echo "FAIL: a forged post was saved"; exit 1; }
  echo "ok   a post without a valid token is ignored"

  T=$(curl -fsS -c $J -b $J "$B/" | grep -o "name=\"token\" value=\"[a-f0-9]*\"" | head -1 | sed "s/.*value=\"\(.*\)\"/\1/")
  curl -fsS -o /dev/null -c $J -b $J --data-urlencode "token=$T" -d privatusAnalyticsSave=1 --data-urlencode "privatusAnalyticsSiteId=pa_x\" onload=\"alert(1)" "$B/"
  curl -fsS -o /tmp/bad.html "$B/"
  if grep -q "privatusstats.com" /tmp/bad.html; then echo "FAIL: bad site id printed a tag"; exit 1; fi
  echo "ok   a bad site id prints no tag"
  if grep -qi "fatal\|warning\|deprecated" /tmp/server.log; then cat /tmp/server.log; echo "FAIL: PHP errors"; exit 1; fi
  echo "ok   no PHP warnings (WonderCMS $VERSION)"
'
