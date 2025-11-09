# Levering - AK4 (Varehus DB)

Dette repositoryet inneholder en enkel web-applikasjon (PHP/Apache) og en MariaDB-database pakket som Docker-tjenester, samt et overvåkningsoppsett (Prometheus, node-exporter, cAdvisor og Grafana). Filene leveres som Dockerfiles per tjeneste og en `docker-compose.yml` som kobler tjenestene sammen.

## Innhold i leveransen

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

## Krav

- Docker (med Compose). På Windows PowerShell anbefales Docker Desktop med Compose V2 slik at kommandoen `docker compose` fungerer.

## Hurtigstart (PowerShell)

1) Bygg image-ene og start alle tjenester (bakgrunn):

```powershell
docker compose build --parallel
docker compose up -d
```

2) Sjekk at containere kjører og eventuelle helse-sjekker er grønne:

```powershell
docker compose ps
docker compose logs -f db
```

3) Web-applikasjonen:

- Åpne: http://localhost:8080 (kan endres ved å sette miljøvariabel `WEB_PORT` i `docker-compose.yml` eller i miljøet før oppstart)
- Standard database-tilkobling er konfigurert via miljøvariabler i `docker-compose.yml`:
  - DB_HOST (default `db`), DB_NAME (default `varehusdb`), DB_USER (default `webuser`), DB_PASS (default `Passord123`)

4) Overvåkning:

- Prometheus UI: http://localhost:9090
- Grafana UI: http://localhost:3000 (default bruker: `admin`, passord: `admin123` — kan endres i `docker-compose.yml`)
- cAdvisor: http://localhost:8081
- Node exporter tilgjengelig på port 9100 (ikke et fint web-UI, men Prometheus scrapes den)

## Hvordan databasen initialiseres

- `db/init.sh` starter MariaDB midlertidig og kjører init-skript:
  - Setter root-passord og oppretter database og bruker basert på miljøvariabler
  - Importerer `dump/varehusdb.sql` dersom filen finnes i `/docker-entrypoint-initdb.d/`

Dumpen er bind-mountet i `docker-compose.yml` slik at den automatisk importeres ved første oppstart.

## Miljøvariabler (valgfrie / kan overstyres)

- DB_ROOT_PASSWORD (default: `rootpass`)
- DB_NAME (default: `varehusdb`)
- DB_USER (default: `webuser`)
- DB_PASS (default: `Passord123`)
- WEB_PORT (default: `8080`)

Du kan starte med egne verdier i PowerShell slik:

```powershell
$env:DB_PASS = 'MinSikrePassord!'
docker compose up -d --build
```

Eller sett dem i en `.env`-fil ved siden av `docker-compose.yml` med samme navn som variablene.

## Testoppskrift / sjekkliste

1. Start tjenestene: `docker compose up -d --build`
2. Kjør `docker compose ps` og sørg for at `db`, `web`, `prometheus` og `grafana` er i `running`-status.
3. Vent til `db`-healthcheck er OK (docker-compose definerer en healthcheck). Sjekk med:

```powershell
docker inspect --format='{{json .State.Health}}' ak4-db
```

4. Åpne web-UI: http://localhost:8080 — du skal se søkegrensesnittet som laster tabeller fra databasen.
   - Bruk nettleserens devtools for å se AJAX-kall til `?action=tables` og `?action=table_data`.
5. Åpne Grafana: http://localhost:3000 og verifiser at dashbordene er lastet (provisioning gjør dette automatisk).
6. Åpne Prometheus: http://localhost:9090 og naviger til Targets for å se at `node_exporter` og `cadvisor` er scraped.

Eksempler for å feilsøke:

```powershell
docker compose logs -f web
docker compose logs -f db
docker compose exec db bash -c "mysql -uwebuser -p\"$env:DB_PASS\" -e 'SHOW TABLES' $env:DB_NAME"
```

NB: I PowerShell må du være oppmerksom på at `$env:VAR` leses fra miljøet og at tegnsetting i inline-kommandoer må escapes riktig.

## Filoversikt

- `docker-compose.yml` - Orkestrering av alle tjenester
- `db/` - Dockerfile og `init.sh` som utfører initialisering og import
- `web/` - Dockerfile + `index.php` + `config.php` (webapplikasjonen)
- `dump/varehusdb.sql` - SQL dump som importeres ved første oppstart
- `monitoring/` - Prometheus og Grafana konfigurering og dashboards

## Vanlige problemer og løsninger

- Container starter men webapp gir 503:
  - Sjekk at `db`-container er ferdig initialisert og helse-sjekken er OK.
  - Se `docker compose logs -f web` for feil ved DB-tilkobling.
- Dump import mislykkes:
  - Sjekk at `dump/varehusdb.sql` finnes og at filrettigheter tillater lesing fra compose-kontekst.
  - Se `docker compose logs -f db` for feilmeldinger fra `init.sh`.
- Grafana ikke viser dashboards:
  - Sjekk at provisioning-mappen er mountet riktig: `./monitoring/grafana/provisioning` og at YAML-filene finnes.
  - Se Grafana-logger: `docker compose logs -f grafana`.

## Quality gates (rask sjekk)

- Build: `docker compose build` (PASS hvis image bygges uten feil)
- Runtime: `docker compose up -d` og `docker compose ps` (tjenestene skal være `running`)

Hvis du ønsker kan jeg også legge til en kort `Makefile` eller PowerShell-skript for enklere kjøring (f.eks. `ps1` med bygg/start/stop/clean). Si ifra om du vil ha det.

---

### Lisens

Leveringen kan distribueres videre etter behov; ingen spesiell lisens er satt i dette repo. Legg til en `LICENSE`-fil om du trenger en bestemt lisens (MIT, Apache-2.0, osv.).

### Kontakt

For spørsmål om oppsettet eller feil: legg en issue i GitHub-repoet eller ta kontakt med leverandør.