# RestaurantApp

Symfony 8-website voor een restaurant met een reserveringssysteem voor tafels, een menu,
en een login voor het restaurantpersoneel. De applicatie draait in Docker (PHP-FPM + Nginx)
met MariaDB 11.4 aan de basis, en gebruikt Doctrine ORM/DBAL en Doctrine Migrations voor de
persistentie en schemawijzigingen.

## Auteursrecht

© 2026 Daniel Klous. Alle rechten voorbehouden.

Dit project is van Daniel Klous. Commercieel gebruik door anderen is niet toegestaan
zonder zijn voorafgaande schriftelijke toestemming. Voor onderdelen van derden, zoals
Symfony en de Unsplash-foto, gelden hun eigen licenties.

## Stacks

| Component | Versie |
| --- | --- |
| PHP | 8.5.12 (image: `php:8.5-fpm`) |
| Composer | 2.x (in de image + host) |
| Symfony | 8.1 (maintained 8.x-lijn) |
| Doctrine ORM | 3.x |
| Doctrine DBAL | 4.x (alleen voor complexe/perf-queries) |
| Doctrine Migrations | 4.x |
| Twig | 3.x |
| MariaDB | 11.4 |
| Nginx | 1.27-alpine |

> Kernprincipe van `AGENTS.md`: alleen Symfony/Doctrine/Twig en ingebouwde componenten
> als fundament — geen eigen framework, router, container, ORM, validatie- of template-systeem.

## Architectuur

```
┌────────────┐   :8080   ┌─────────────┐  :9000 (fpm)  ┌──────────────┐   :3306   ┌──────────────────┐
│   Browser   │ ────────> │   Nginx     │ ─────────────> │  PHP-FPM     │ ─────────> │  MariaDB 11.4    │
│ (localhost) │  HTML/JS  │  (web)      │  fastcgi_pass  │  (php)       │  PDO        │ (db)            │
└────────────┘ <──────────└─────────────┘ <────────────── └──────────────┘ <───────── └──────────────────
                ←──────────────────  HTTP-response  ←────────────────────────────
```

Alle drie de services delen de code via een bind-mount (`.` → `/var/www`). Nginx luistert op
`8080` (naar de host), praat intern met PHP-FPM op `9000`, en de app praat met MariaDB op `3306`.

## Vereisten

- Docker + Docker Compose (op deze machine: Docker 29.8, Compose v5.6.0)
- Composer (voor dependency-installatie op de host)
- PHP 8.5.12 op de host is **optioneel** — voor de dagelijkse workflow draait alles in Docker.

## Opstarten vanaf nul

```bash
# 1) (optioneel) kopieer de voorbeeld-omgeving en pas aan waar nodig
cp .env.example .env

# 2) installeer de Symfony/Doctrine-afhankelijkheden
composer install

# 3) bouw de containers (beoogt: DB, PHP-FPM, Nginx)
docker compose build

# 4) start alles. De php-service wacht tot de DB 'healthy' is.
docker compose up -d

# 5) open de app
curl -s http://localhost:8080/          # of: ga naar http://localhost:8080/ in de browser
```

## Verificatiecommando's

```bash
# PHP-rundering binnen de container
docker compose exec -T php php -v

# Applicatie draait (Twig-generieke startpagine)
curl -sI http://localhost:8080/ | head -n1        # verwach: HTTP/1.1 200 OK
curl -s  http://localhost:8080/                    # verwacht: HTML met "RestaurantApp"

# Database is bereikbaar
docker compose exec -T db mariadb-admin ping --silent

# Doctrine-CLI is present (migrations-infra, zonder migraties)
docker compose exec -T php php bin/console
docker compose exec -T php php bin/console doctrine:migrations:status
docker compose exec -T php php bin/console debug:router

# logs
docker compose logs -f web php db
# alles ophalen
docker compose down
```

## Dagelijks werk met MariaDB / migrations

Alle console-commando's lopen via de `php`-container, zodat `DATABASE_URL`
consistent op de interne hostname `db` wijst:

```bash
# een nieuwe migratie genereren (na het aanmaken/anpassen van een entiteit)
docker compose exec -T php php bin/console doctrine:migrations:diff

# togepaste migraties
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction

# schema ophalen (geen productie!)
docker compose exec -T php php bin/console doctrine:schema:drop --full-database
docker compose exec -T php php bin/console doctrine:schema:create
```

## Services, poorten en volumes

| Service | Image | Externe poort | Container-poort | Volumes | Netwerk-naam |
| --- | --- | --- | --- | --- | --- |
| `db` | `mariadb:11.4` | `3306:3306` | `3306` | `db_data` (named, `/var/lib/mysql`) | `db` |
| `php` | `php:8.5-fpm` (build) | — | `9000` | `.` → `/var/www` | `php` |
| `web` | `nginx:1.27-alpine` | `8080:80` | `80` | `.` → `/var/www` (ro), `default.conf` (ro) | `web` |

> Let op: poort `3306` kan op de host bezet zijn door een lokaal draaiend MySQL. Als
> `docker compose up` faalt met *port already allocated*, stop dat proces (bv.
> `brew services stop mysql`) of wijzig de host-poort in `docker-compose.yml`.

- **Healthcheck** op `db`: `mariadb-admin ping --silent`; de `php`-service start pas wanneer de DB `healthy` is.
- `php` luistert op `0.0.0.0:9000` (zodat Nginx, in een aparte container, bereikbaar is),
  zie `docker/php/php-fpm.d/www.conf`.

## Environment variables

Zie `.env.example` (veilige voorbeeldwaarden) en `.env` (actief, met placeholders voor de dev-wereld).

| Variabele | Gebruikt door | Beschrijving / voorbeeld |
| --- | --- | --- |
| `APP_ENV` | Symfony | `dev` |
| `APP_DEBUG` | Symfony | `1` (dev) |
| `APP_SECRET` | Symfony framework-bundle | sessie/CSRF-secret — voor echte waarde gebruik `.env.local` |
| `DATABASE_URL` | Doctrine (host-CLI) | `mysql://restaurant:restaurant_secret_dev@127.0.0.1:3306/restaurant?charset=utf8mb4` |
| `MARIADB_DATABASE` | `db` | naam van de DB — `restaurant` |
| `MARIADB_USER` | `db` | standaard-gebruiker — `restaurant` |
| `MARIADB_PASSWORD` | `db` | wachtwoord — `restaurant_secret_dev` |
| `MARIADB_ROOT_PASSWORD` | `db` | root-wachtwoord — `restaurant_root_secret_dev` |

### Hoe secrets worden beheerd

`docker compose` leest `.env` (projectroot) automatisch voor interpolatie — dus
`MARIADB_*` uit `.env` vult de `db`-service. Voor de `php`-service *overschrijft*
de `environment:`-blok binnen `docker-compose.yml` `DATABASE_URL` naar de interne
hostname `db` (zodat de app de DB bereikt zonder `127.0.0.1`).

- Echte secrets gaan naar **`.env.local`** (ge-gitignore'd) of een secrets-store
  (`composer symfony/secrets`).
- `.env` en `.env.example` bevatten alleen placeholders — **nooit echte secrets**.

## Mapstructuur

```
.
├── .env                     # actief — placeholders (committable)
├── .env.example             # referentie met veilige voorbeelden
├── .dockerignore            # houdt de build-context klein
├── docker-compose.yml       # 3 services: db, php, web
├── docker/
│   ├── php/
│   │   ├── Dockerfile          # php:8.5-fpm + pdo_mysql + composer
│   │   └── php-fpm.d/
│   │       └── www.conf        # luistert op 0.0.0.0:9000
│   └── nginx/
│       └── default.conf        # root /var/www/public, fastcgi_pass php:9000
├── config/packages/         # Symfony-config (framework, doctrine, twig, …)
├── src/
│   ├── Kernel.php
│   ├── Controller/          # #[Route]-attributen auto-gedetecteerd
│   ├── Entity/              # lege entiteiten-map (nog geen entiteiten)
│   ├── Migrations/          # namespace App\Migrations
│   └── Repository/
├── templates/               # Twig
│   ├── base.html.twig
│   └── home/index.html.twig
└── public/
    └── index.php            # front-controller
```

## Dagelijks gebruik

- De site is beschikbaar in Nederlands, Engels, Duits, Frans en Italiaans via `/nl/`, `/en/`, `/de/`, `/fr/` en `/it/`. De taalkeuze houdt de huidige pagina en eventuele parameters vast; oude URL's blijven bereikbaar.
- Vertalingen staan in `translations/messages.<taal>.php` en `translations/menu.<taal>.php`. Eigen menugegevens blijven in de oorspronkelijke taal zichtbaar totdat ze in alle menucatalogi zijn vertaald.
- De homepage en menukaart zijn publiek. Op de reserveringspagina kunnen gasten bellen via het opgegeven telefoonnummer.
- De sfeerfoto op de homepage is afkomstig van [Unsplash](https://images.unsplash.com/photo-1541167760496-1628856ab772) en wordt gebruikt onder de [Unsplash-licentie](https://unsplash.com/license).
- De plattegrond op de homepage toont de actieve tafels `Tafel 1` t/m `Tafel 6`: 1–4 binnen en 5–6 op het terras. Het aantal zitplaatsen komt uit de database; de indeling en posities staan in `HomeController`. Andere tafels en dubbele tafelnamen worden niet op de plattegrond geplaatst. De plattegrond toont geen actuele beschikbaarheid.
- Alleen personeel kan via `/nl/login` inloggen en in `/nl/dashboard` tafelnummer, datum, begintijd, gezelschap en duur vastleggen. Een opgeslagen reservering staat direct als gereserveerd.
- Maak zo nodig een nieuw rootaccount met `docker compose exec -T php php bin/console app:create-root naam@voorbeeld.nl`. Het willekeurige wachtwoord wordt eenmaal getoond; een bestaand account wordt niet aangepast. Bewaar het wachtwoord veilig.
- Tijden zijn 24/7 beschikbaar. Reserveringen blokkeren hun tafel voor de ingestelde duur (standaard 90 minuten, instelbaar van 15 minuten tot 24 uur); aansluitende tijdvakken zijn toegestaan.
- Een reservering annuleren maakt de tafel weer vrij. Een reservering opnieuw activeren wordt opnieuw op conflicten gecontroleerd.
- Demo-tafels, personeelsaccount en voorbeeldmenu kunnen idempotent worden aangemaakt met:

  ```bash
  docker compose exec -T php php bin/console app:seed
  ```

  Als er nog geen personeelsaccount bestaat, toont dit commando een nieuw gegenereerd e-mailadres en wachtwoord. Bewaar dit veilig; het wachtwoord wordt niet opnieuw getoond. De seeder voegt menu-items toe en werkt uitsluitend exact herkende, ongewijzigde demo-items bij; overige bestaande items blijven behouden.
- Schiphol Airport is de opgegeven locatie. Het telefoonnummer staat bij `phone` in `src/Twig/RestaurantExtension.php`; zonder nummer is telefonisch reserveren niet beschikbaar.
- Menuprijzen, recepturen, pizzamaten, ingrediënten, allergenen en dieetlabels zijn niet geverifieerd. Laat ze door het restaurant controleren, inclusief kruisbesmetting en de 14 EU-allergenen; onbekend betekent niet allergenenvrij. Voeg halal- of koosjerclaims pas toe na controle van ingrediënten en bereiding. De `18+`-vermelding is geen leeftijdscontrole: personeel moet bij alcoholverstrekking de leeftijd controleren.

## Databasewijzigingen

Na het ophalen van wijzigingen pas je migraties toe met:

```bash
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction
```

De reserveringsmigratie geeft bestaande afspraken een standaardduur van 90 minuten. Bestaande gegevens en eventuele historische overlappingen blijven behouden.

## Troubleshooting

- **502 Bad Gateway van Nginx**: `docker compose logs php db` — fpm draait mogelijk
  nog niet (start_period), of `DATABASE_URL` wijst naar een onbereikbare host.
- **`Doctrine\DBAL\Exception\DriverException` binnen Docker**: controleer of
  `docker compose exec -T php env | grep DATABASE_URL` de `db`-waarde toont.
- **Migratie-CLI kan verbinden met de DB niet**: controleer of de `php`-service
  de `db`-healthcheck nog wacht (`docker compose ps`).
- **Op Linux schrijft het niet naar `./var`**: de fpm-gebruiker (`www-data`) moet
  dezelfde uid/gid hebben als de host-gebruiker.
