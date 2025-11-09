# AK4 (Varehus DB)

Dette repositoryet inneholder en enkel web-applikasjon (PHP/Apache) og en MariaDB-database pakket som Docker-tjenester, samt et overvåkningsoppsett (Prometheus, node-exporter, cAdvisor og Grafana). Filene leveres som Dockerfiles per tjeneste og en `docker-compose.yml` som kobler tjenestene sammen.

## Forutsetninger

For å kjøre dette systemet trenger du:
- Docker Engine (versjon 20.10.0 eller nyere)
- Docker Compose V2 (inkludert i Docker Desktop)
- Minimum 2GB ledig RAM (for alle containere)
- Ca 1GB ledig diskplass
- Git (for å klone repositoryet)
- Følgende porter må være tilgjengelige lokalt:
  - 8080 (web-applikasjon)
  - 3306 (MariaDB, internt)
  - 9090 (Prometheus)
  - 3000 (Grafana)
  - 8081 (cAdvisor)
  - 9100 (Node exporter)

## Innhold

- Dockerfile for hver tjeneste:
  - `db/Dockerfile` (MariaDB/MariaDB-server + init-script)
  - `web/Dockerfile` (Apache + PHP web-applikasjon)
- `docker-compose.yml` for å bygge og starte alle tjenester
- Database-dump: `dump/varehusdb.sql` (importeres ved første oppstart)
- Overvåkning:
  - Prometheus: `monitoring/prometheus/prometheus.yml`
  - Grafana provisioning: `monitoring/grafana/provisioning/` og dashboards i `monitoring/grafana/dashboards/`

## Kort om arkitektur

- Tjenester definert i `docker-compose.yml`:
  - `db` - MariaDB-database (init-script `db/init.sh` importerer dump ved første oppstart)
  - `web` - PHP/Apache-applikasjon (kopierer `web/index.php` og `web/config.php`)
  - `prometheus` - Prometheus for tidsserieinnsamling
  - `node-exporter` - Metrics fra host
  - `cadvisor` - Container-metrikker
  - `grafana` - Dashboard for overvåkning
- Nettverk: alle tjenester kobles til nettverket `ak4net`.

## Start

1) Bygg image-ene og start alle tjenester (bakgrunn):

```
git clone https://github.com/ektealexander/ak4-linux.git
cd ak4-linux
docker compose up -d --build
```

3) Applikasjonene:

- Åpne: http://localhost:8080
- Prometheus UI: http://localhost:9090
- Grafana UI: http://localhost:3000 (default bruker: `admin`, passord: `admin123`)
- cAdvisor: http://localhost:8081
- Node exporter tilgjengelig på port 9100 (ikke et fint web-UI, men Prometheus scrapes den)

## Hvordan databasen initialiseres

- `db/init.sh` starter MariaDB midlertidig og kjører init-skript:
  - Setter root-passord og oppretter database og bruker basert på miljøvariabler
  - Importerer `dump/varehusdb.sql` dersom filen finnes i `/docker-entrypoint-initdb.d/`

Dumpen er bind-mountet i `docker-compose.yml` slik at den automatisk importeres ved første oppstart.

## Filoversikt

- `docker-compose.yml` - Orkestrering av alle tjenester
- `db/` - Dockerfile og `init.sh` som utfører initialisering og import
- `web/` - Dockerfile + `index.php` + `config.php` (webapplikasjonen)
- `dump/varehusdb.sql` - SQL dump som importeres ved første oppstart
- `monitoring/` - Prometheus og Grafana konfigurering og dashboards