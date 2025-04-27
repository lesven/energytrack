# EnergyTrack - Energie-Verbrauchsmanagement

EnergyTrack ist eine Anwendung zur Verwaltung und Analyse von Energieverbrauchsdaten. Mit dieser Anwendung können Sie Zählerstände erfassen, vergleichen und analysieren.

## Voraussetzungen

- Docker und Docker Compose (für die containerisierte Installation)
- PHP 8.1 oder höher (für lokale Installation)
- Composer
- Node.js und npm/yarn
- Git

## Installation

### Option 1: Mit Docker (empfohlen)

1. Klonen Sie das Repository:
   ```bash
   git clone <repository-url>
   cd energytrack
   ```

2. Starten Sie die Docker-Container:
   ```bash
   docker-compose up -d
   ```

3. Installieren Sie die PHP-Abhängigkeiten:
   ```bash
   docker-compose exec php composer install
   ```

4. Installieren Sie die JavaScript-Abhängigkeiten und kompilieren Sie die Assets:
   ```bash
   npm install
   npm run build
   ```

5. Erstellen Sie die Datenbank-Tabellen:
   ```bash
   docker-compose exec php bin/console doctrine:migrations:migrate
   ```

6. Die Anwendung ist jetzt unter `http://localhost:8080` erreichbar.

### Option 2: Lokale Installation

1. Klonen Sie das Repository:
   ```bash
   git clone <repository-url>
   cd energytrack
   ```

2. Installieren Sie die PHP-Abhängigkeiten:
   ```bash
   composer install
   ```

3. Konfigurieren Sie die Datenbankverbindung in `.env` oder erstellen Sie eine `.env.local`:
   ```
   DATABASE_URL="mysql://benutzer:passwort@127.0.0.1:3306/energytrack?serverVersion=8.0"
   ```

4. Erstellen Sie die Datenbank und die Tabellen:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. Installieren Sie die JavaScript-Abhängigkeiten und kompilieren Sie die Assets:
   ```bash
   npm install
   npm run build
   ```

6. Starten Sie den lokalen Entwicklungsserver:
   ```bash
   php bin/console server:start
   ```

7. Die Anwendung ist jetzt unter `http://localhost:8000` erreichbar.

## Erste Schritte

1. Erstellen Sie zunächst einen oder mehrere Zähler über die Menüoption "Zähler verwalten"
2. Erfassen Sie Zählerstände für Ihre Zähler
3. Nutzen Sie die Analyse-Funktionen, um Ihren Energieverbrauch auszuwerten

## Funktionen

- Verwaltung verschiedener Zählertypen (Strom, Gas, Wasser, etc.)
- Erfassung von Zählerständen mit Datum
- Analyse des Verbrauchs über verschiedene Zeiträume
- Vergleich des Verbrauchs zwischen verschiedenen Zeiträumen
- Import und Export von Zählerständen

## Entwicklung

### Code-Struktur

- `src/Controller/`: Enthält die Controller der Anwendung
- `src/Entity/`: Enthält die Datenbankentitäten
- `src/Repository/`: Enthält die Repositories für Datenbankabfragen
- `src/Service/`: Enthält Dienste für die Geschäftslogik
- `templates/`: Enthält die Twig-Templates für die Benutzeroberfläche
- `assets/`: Enthält JavaScript- und CSS-Dateien

### Tests ausführen

```bash
php bin/phpunit
```

## Problembehebung

Bei Problemen während der Installation:

1. Überprüfen Sie, ob alle Voraussetzungen erfüllt sind
2. Prüfen Sie die Logdateien in `var/log/`
3. Löschen Sie den Cache mit `php bin/console cache:clear`