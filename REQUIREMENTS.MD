# Project Requirements Document: Verbrauchsdaten-Applikation

## Vision

Die Applikation soll es ermöglichen, private Strom-, Gas- und Wasserverbrauchsdaten einfach zu erfassen, auszuwerten und miteinander zu vergleichen.  
Ziel ist es, den Überblick über den eigenen Ressourcenverbrauch zu behalten und Entwicklungen über Jahre hinweg nachvollziehen zu können.

---

## Technologie-Stack

- **Backend:** Symfony 7.2 (PHP)
- **Datenbank:** MariaDB
- **Frontend:** Einfaches HTML mit Bootstrap
- **Deployment:** Docker Compose

---

## Hauptfunktionen

### Erfassung von Zählerständen

- Nutzer kann neue Zählerstände direkt in der App erfassen.
- Zählertypen: `electricity`, `gas`, `water`.
- Eingabe eines Datums, Standardwert: aktuelles Datum.
- Eingabe eines Zählerstandes (Dezimalzahl erlaubt).

### Import/Export von Daten

- **Import:** CSV-Datei mit Spalten `meter_type`, `reading_value`, `reading_date` (Datum im Format `YYYY-MM-DD`).
  - Der gesamte Import wird abgebrochen, wenn eine fehlerhafte Zeile erkannt wird.
  - Eine Fehlermeldung zeigt den Grund des Abbruchs an (z.B. ungültiges Datum oder Wert).
- **Export:** CSV-Datei mit denselben Spalten, um Daten zu sichern oder weiterzuverarbeiten.

### Verbrauchsauswertung

- Anzeige des durchschnittlichen Verbrauchs pro Woche, Monat und Quartal.
- Summierung des Verbrauchs für beliebige selbst wählbare Zeiträume.
- Vergleich beliebiger Zeiträume miteinander (z.B. erste 100 Tage 2025 vs. erste 100 Tage 2024).

### Darstellung

- Übersichtliche Anzeige der Verbrauchsdaten in einfacher numerischer Form (keine Diagramme).

---

## Nicht-Funktionale Anforderungen

### Performance

- Die Anwendung ist für eine Nutzung mit einigen Hundert Einträgen ausgelegt.
- Optimierungen für sehr große Datenmengen sind nicht notwendig.

### Sicherheit

- Die Anwendung wird lokal oder im Heimnetzwerk betrieben.
- Kein Benutzer-Login oder Passwortschutz erforderlich.

### Frontend

- Funktionales, schlichtes Frontend auf Basis von Bootstrap.
- Keine aufwendige UI oder Animationen.

### Backup und Restore

- Es erfolgt kein komplettes Backup/Restore der Datenbank über die App.
- CSV-Import und Export decken die Sicherung und Wiederherstellung der Verbrauchsdaten ab.

---

## Deployment

Eine Docker Compose-Datei wird bereitgestellt, die folgende Services umfasst:

- PHP-FPM Container für Symfony
- Nginx Webserver
- MariaDB-Datenbank
- Adminer für einfache Datenbankverwaltung (optional)
