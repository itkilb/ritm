# Production deployment «Ритм»

Отдельный production-стек для Ubuntu 26.04 x86_64 (1 CPU, 956 MB RAM + 1 GB
swap). Он не наследует `docker-compose.yml`: это самостоятельный compose-файл.
Приложение и существующие Dockerfile не меняются.

```
Caddy :80/:443  ->  web:80 (nginx + SPA)  ->  backend:9000 (PHP-FPM)
                                                ->  mysql:3306
```

- Публикует порты только `caddy`: `80/tcp` и `443/tcp`. У mysql, backend и web
  нет host-портов.
- `mysql` и `backend` подключены только к внутренней сети `ritm_prod_internal`.
  `web` дополнительно подключён к `ritm_prod_edge`, где находится Caddy.
- Caddy завершает TLS, использует сертификат Certbot, подтверждённый через DNS TXT и переписывает
  `X-Forwarded-Proto`/`X-Forwarded-For`. nginx доверяет этим заголовкам только
  от приватных сетей и передаёт в FastCGI корректные `HTTPS`,
  `REQUEST_SCHEME`, `SERVER_PORT` и реальный `REMOTE_ADDR`.
- `www.<домен>` постоянно перенаправляется на корневой домен. Laravel видит
  HTTPS, поэтому secure/HttpOnly/SameSite=Lax cookies и same-origin CSRF
  работают так же, как в локальном окружении.

## 1. Секреты

На сервере, из корня репозитория (`/opt/ritm`):

```bash
cd /opt/ritm
cp deploy/.env.example .env.production
chmod 600 .env.production
$EDITOR .env.production
```

Заполните `DOMAIN`, `APP_KEY` (`openssl rand -base64 32` с префиксом
`base64:`), `HASH_ID_KEY` (`openssl rand -hex 32`), `MYSQL_PASSWORD` и
`MYSQL_ROOT_PASSWORD`. Файл `.env.production` не коммитится и не передаётся
контейнерам целиком: Compose использует его только для подстановки значений.
Поэтому `MYSQL_ROOT_PASSWORD` не попадает в backend.

## 2. Образы (собираются вне сервера)

Стек не содержит `build:`. Соберите linux/amd64-образы на рабочей машине или
в CI, затем перенесите их на сервер:

```bash
docker buildx build --platform linux/amd64 \
  --build-arg INSTALL_DEV_DEPS=false \
  -f docker/php/Dockerfile -t ritm-backend:b950e2e-amd64 --load .

docker buildx build --platform linux/amd64 \
  --build-arg VITE_API_BASE_URL= \
  -f docker/web/Dockerfile -t ritm-web:b950e2e-amd64 --load .
```

`INSTALL_DEV_DEPS=false` обязателен: в production-образе не должно быть dev-
зависимостей. Проверка архитектуры:

```bash
docker image inspect ritm-backend:b950e2e-amd64 --format '{{.Os}}/{{.Architecture}}'
docker image inspect ritm-web:b950e2e-amd64 --format '{{.Os}}/{{.Architecture}}'
# linux/amd64
```

Перенос без registry (подставьте свой SSH-хост; этот репозиторий SSH не
использует и на сервер не подключается):

```bash
docker save ritm-backend:b950e2e-amd64 ritm-web:b950e2e-amd64 \
  | gzip -1 | ssh user@server 'gunzip -c | docker load'
```

Альтернатива — опубликовать эти же теги в registry и выполнить `docker pull`
на сервере. Менять теги в `.env.production` (`BACKEND_IMAGE`, `WEB_IMAGE`)
можно без изменения compose-файла.

## 3. Запуск, миграции, config cache

Все команды выполняются из `/opt/ritm`:

```bash
docker compose --env-file .env.production -f deploy/compose.production.yml config --quiet
docker compose --env-file .env.production -f deploy/compose.production.yml up -d

docker compose --env-file .env.production -f deploy/compose.production.yml \
  exec -u www-data backend php artisan migrate --force

docker compose --env-file .env.production -f deploy/compose.production.yml \
  exec -u www-data backend php artisan config:cache
docker compose --env-file .env.production -f deploy/compose.production.yml \
  restart backend
```

`config:cache` обязателен после каждого пересоздания backend-контейнера
(`up -d` с новым образом): файл кэша находится в контейнере, а не в volume.
После `config:cache` перезапустите backend, потому что в php.ini отключена
проверка времени изменения файлов (`opcache.validate_timestamps=0`).

`APP_TIMEZONE=UTC` — только серверное значение по умолчанию. Часовой пояс
пользователя хранится в профиле как IANA timezone и не подменяется на
серверный.

Проверка состояния:

```bash
docker compose --env-file .env.production -f deploy/compose.production.yml ps
docker compose --env-file .env.production -f deploy/compose.production.yml \
  logs --tail=100 caddy
```

## 4. DNS и HTTPS

- Создайте `A`-записи для `example.com` и `www.example.com` на публичный IPv4
  сервера.
- `AAAA` добавляйте только если IPv6 на сервере реально работает и открыт
  снаружи. Иначе Let's Encrypt может выбрать IPv6 и не пройти проверку.
- Для HTTP-редиректа и HTTPS нужны входящие `80/tcp` и `443/tcp`.
  Проверка Certbot в текущей конфигурации проходит через DNS TXT.
  UDP/443 (HTTP/3) в Caddyfile отключён, потому что публикуется только TCP.
- Caddy читает сертификат Certbot из `/etc/letsencrypt`. Его служебное состояние хранится в
  `ritm_prod_caddy_data` и `ritm_prod_caddy_config`.

## 5. Данные и обновления

Именованные production-volume:

| volume | назначение |
| --- | --- |
| `ritm_prod_mysql_data` | база MySQL |
| `ritm_prod_backend_storage` | `storage/`, включая Passport-ключи |
| `ritm_prod_caddy_data` | служебное состояние Caddy |
| `ritm_prod_caddy_config` | конфигурация Caddy |

`docker compose ... down` без `-v` сохраняет данные. **Не используйте**
`down -v`, `docker volume prune`, `docker system prune`, `docker image prune`
и удаление volume/образов: это разрушительно для production. `mysql.cnf` не
меняет `datadir` и не содержит init-скриптов, удаляющих данные. Штатный
entrypoint backend-образа создаёт `.env`, Passport-ключи и готовит `storage`
в persistent-volume. `.env.production` и содержимое всех volume — состояние
сервера, а не файлы git.

Обновление:

1. Соберите/загрузите новые amd64-образы и обновите `BACKEND_IMAGE`/`WEB_IMAGE`
   в `.env.production`.
2. `docker compose ... up -d` (без `--build`).
3. Выполните `migrate --force`, затем `config:cache` и `restart backend`.

## 6. Ограничения ресурсов

`mem_limit` — верхняя граница, не резервирование RAM: backend 256 MB (swap до
512 MB), mysql 384 MB (swap до 768 MB), web 64 MB, Caddy 128 MB с
`GOMEMLIMIT=64MiB`. Swap-файлом VM стек не управляет. PHP: 2 FPM-воркера
`ondemand`, 128 MB памяти, OPcache 64 MB, `validate_timestamps=0`. MySQL:
buffer pool 64 MB, log buffer 8 MB, redo 64 MB, temp-таблицы 16 MB,
20 соединений, `table_open_cache=200`, `table_definition_cache=400`,
Performance Schema и X Plugin выключены.

## 7. Проверки

Конфигурации проверяются без запуска приложения:

```bash
docker compose --env-file .env.production -f deploy/compose.production.yml config --quiet

docker run --rm \
  -v "$PWD/deploy/nginx.conf:/etc/nginx/conf.d/default.conf:ro" \
  nginx:1.27-alpine nginx -t

docker run --rm -e DOMAIN=example.com \
  -v "$PWD/deploy/Caddyfile:/etc/caddy/Caddyfile:ro" \
  caddy:2-alpine caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
```

Эти команды не подключаются к production и не меняют существующие контейнеры.
Полные backend/frontend-тесты приложения для deployment-изменений не нужны.
Файлы `deploy/*` — режим `644`, каталог `deploy/` — `755`.

## Сертификат через DNS TXT

Текущий Caddyfile читает сертификат `example.com` из `/etc/letsencrypt` на сервере (read-only mount). Перед запуском Caddy выполните:

```bash
certbot certonly --manual --preferred-challenges dns --agree-tos \
  --register-unsafely-without-email --cert-name example.com \
  -d example.com -d www.example.com
```

Добавьте предложенные TXT и дождитесь проверки. Ручной DNS challenge не поддерживает автоматическое продление без DNS API/hook. До истечения сертификата повторите выпуск с новыми TXT и перезапустите только Caddy. Старые challenge TXT после успешного выпуска можно удалить. Неверная AAAA продолжает направлять IPv6-клиентов на другой сервер даже с действующим сертификатом.

### Текущее развёртывание

При первом запуске из-за медленного канала был перенесён компактный runtime из проверенного production-образа: приложение с vendor, PHP-расширения, их shared libraries и entrypoint. На сервере он собран поверх того же `php:8.2-fpm-bookworm`; исходники приложения не менялись. Рабочая папка сборки — `/opt/ritm-backend-runtime`, архив — `/opt/ritm-backend-runtime.tar.gz`. Интерфейс перенесён как готовый dist из проверенного образа и собран поверх nginx. Для последующих обновлений можно использовать стандартную сборку образов выше.
