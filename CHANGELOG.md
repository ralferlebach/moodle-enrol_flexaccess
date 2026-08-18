# Changelog

## 0.1.18 — 2026-08-17
- **Linting robust fuer aeltere moodle-cs gemacht (die lokale `make check`-Umgebung nutzt eine strengere/aeltere moodle-cs als die CI):** `@package`-Tag in jedem Datei-, Klassen-/Interface-/Trait- und Top-Level-Funktions-Docblock ergaenzt (aeltere moodle-cs verlangt dies ueberall; neuere ab 3.6 hat es gelockert). Test-Klassen erhielten `@covers` auf die jeweils geprueften Klassen (behebt die `missing coverage information`-Warnungen). **Gegengeprueft:** die echte CI (moodle-plugin-ci 4.5.11) meldet weiterhin 0 Verstoesse, PHPUnit auf Moodle 5.3dev bleibt gruen.

## 0.1.17 — 2026-08-17
- **Real auf Moodle 5.3dev (branch 503, PG17) verifiziert — PHPUnit gruen, phpcs 0/0.** Dabei behobene echte Fehler: fehlende Capability-Sprachstrings (flexaccess:config, flexaccess:manage, flexaccess:unenrol) ergaenzt (Core tool_capability-Check); install.xml ins kanonische XMLDB-Format regeneriert (xmlns:xsi + Schema-Location, Komma-Spacing im Index 'scope, scopeid, kind'); access_controller-Tests gruen nach Behebung des Username-Bugs in auth_flexaccess.
- **CI grün gemacht (phpcs, real verifiziert mit moodlehq/moodle-cs v3.7):** Sprachdateien alphabetisch sortiert + `@package` ergänzt (Moodle LangFilesOrdering); einzeilige Docblocks in Mehrzeilenform mit Beschreibungszeile überführt; Multiline-Funktionsaufrufe per phpcbf normalisiert; unnötige `MOODLE_INTERNAL`-Checks entfernt; Konstanten-Docblocks ergänzt.
- **Makefile:** Vorlage übernommen und an das Plugin-Verzeichnis angepasst (PLUGIN_NAME/PLUGIN_REL/MOODLE_ROOT); `make check` zeigt nur Fails, läuft volle Lintings + PHPUnit.
- **GitHub-Workflows:** getrennt für Development (`moodle-ci.yml`, branches-ignore main) und Main (`moodle-release.yml`); zusätzlich `playwright.yml` und `load.yml` bereitgestellt. Von vimipad-spezifischen Bundle/AMD/Node-Schritten befreit; Behat-Tags und Pfade je Komponente. `.gitattributes`/`.gitignore` adaptiert.
- `allow_unenrol`/`allow_unenrol_user` Docblocks, `MOODLE_INTERNAL` aus lib.php/db/upgrade.php entfernt.

## 0.1.16 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.16 (keine funktionale Änderung).

## 0.1.15 — 2026-08-17
- **Zugangs-Controller (End-to-End):** `local\access_controller::grant_temporary_access()` komponiert die getesteten Bausteine — effektive Policy + Access Window prüfen, Kapazität, temporären Nutzer via `auth_flexaccess\api` anlegen, kapazitätsgesichert einschreiben, Follow-up planen. Rückgabe granted/closed/notallowed/notenabled/full. PHPUnit `access_controller_test` (Grant, geschlossenes Fenster ohne Nebenwirkung, Vollbelegung). Kein Schema-Change.

## 0.1.14 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.14 (keine funktionale Änderung).

## 0.1.13 — 2026-08-17
- **Kapazitätsgesicherte Laufzeit-Einschreibung:** neuer `local\enrol_service::enrol_with_capacity()` führt "aktive zählen + einschreiben" atomar im Instanz-Lock aus (Race-frei), lehnt bei Vollbelegung ab (`full`), respektiert deaktivierte Instanzen (`notenabled`) und leitet `timeend` aus der Einschreibedauer ab. PHPUnit `enrol_service_test` (Limit erzwungen, unbegrenzt, deaktiviert, Enrolment-Periode). Kein Schema-Change.

## 0.1.12 — 2026-08-17
- **Identitätsabhängige Restriktionen** (Rollen/Cohorts) in `get_effective_policy(..., $userid)`: neuer reiner `local\restriction_evaluator` (deny gewinnt, Allow-Liste) + `local\restriction_service` (System-/Kategorie-/Kurs-Scope, Rollen im Kurskontext, Cohort-Mitgliedschaft). Restringierte Nutzer verlieren die Flex-Methoden (normaler Login unberührt). Neue Facade `api::is_user_permitted()`. PHPUnit `restriction_evaluator_test`, `restriction_service_test`. Kein Schema-Change.

## 0.1.11 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.11 (keine funktionale Änderung).

## 0.1.10 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.10 (keine funktionale Änderung).

## 0.1.9 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.9 (keine funktionale Änderung).

## 0.1.8 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.8 (keine funktionale Änderung).

## 0.1.7 — 2026-08-17
- **CI-Fix:** `allow_unenrol()` auf die Parent-Signatur (`stdClass $instance`) korrigiert (war ein Install-Fatal); per-User-Variante als `allow_unenrol_user()` ergänzt.
- **CI-Fix:** pgsql-Workflow: vorab-`createdb`-Zeile entfernt (verursachte "database moodle already exists"); moodle-plugin-ci legt die DB selbst an.
- phpcs: eine zu lange Zeile in `policy_assembler` umgebrochen.

## 0.1.6 — 2026-08-17
- Lockstep-Versionsschub auf 0.1.6 (keine funktionale Änderung).

## 0.1.5 — 2026-08-17
- **M-E1: öffentliche Facade `enrol_flexaccess\api`** (`is_target_enabled`, `get_effective_policy`, `get_active_enrolment_count`) — der stabile Vertrag für auth/tool/mod (runtime-lazy, ADR-010).
  - `local\policy_assembler`: effektive Policy aus System → Kategorie-Ahnenkette → Kursinstanz; Verbote restriktiv vererbt (`allowwidening`), Default-Werte (Laufzeiten/Fenster/Kapazität) überschreibbar.
  - `local\access_gate`: kombiniert Fenster + Kapazität zu anbietbaren Methoden (Fenster sperrt Flex-Methoden, nicht den normalen Login; Kapazität sperrt nur einschreibende Methoden).
  - PHPUnit: `access_gate_test`, `policy_assembler_test` (System-Config, Instanz-Anwendung, Ziel-nicht-aktiv, aktive Zählung). Kein Schema-Change.

## 0.1.4 — 2026-08-17
- **E-1b/E-2b: Instanzformular + Persistenz.** Standard-Editing-UI (`use_standard_editing_ui`), Formularfelder für Access Window (`availablefrom`/`availableuntil`, optionale date_time_selector) und `maxparticipants`, Validierung (Fenster-Range über `access_window::is_valid_range`, Kapazität ≥ 0). Persistenz der erweiterten Instanzkonfiguration über neue Klasse `local\instance_config` (add/update/delete). PHPUnit `instance_config_test` (Round-Trip add→update→delete). Behat `instance_access.feature`. Kein Schema-Change.

## 0.1.3 — 2026-08-17
- **E-1 Access Window + E-2 Kapazität (Domänenlogik, Schema, Tests).**
  - Neue Instanzfelder `availablefrom`, `availableuntil`, `maxparticipants` (install.xml + `db/upgrade.php`, Savepoint 2026081730).
  - `policy`-Wertobjekt um Fenster-/Kapazitätsfelder + `is_within_window()`/`is_capacity_unlimited()` erweitert.
  - Neue Klassen `local\access_window` (reine Fensterlogik, untere Grenze inklusiv, obere exklusiv, 0 = unbegrenzt) und `local\capacity_service` (Zählung **aktiver** `user_enrolments`, Prädikat, `is_full`, lock-gesicherter `run_with_lock`).
  - PHPUnit: `access_window_test`, `capacity_service_test` (inkl. Generator-Test: aktiv/suspendiert/abgelaufen).
  - EN/DE-Strings ergänzt. Instanz-Formular-Anbindung folgt als nächste Mikro-Iteration.

## 0.1.2 — 2026-08-17
- Scope-Erweiterung (Planung/Doku): **Kapazitätslimit** (aktive Einschreibungen, ADR-011) und **Access Window** (available from/until, kombinierbar mit Zugangsschlüssel, ADR-012) verbindlich aufgenommen. Siehe `../../docs/Arbeitsplanung.md`.

## 0.1.1 — 2026-08-17
- Version scheme moved to incremental `0.1.x` (release `0.1.1`).
- Dependency model documentation aligned to the ecosystem hard/cycle model (ADR-010); `enrol → auth` hard dependency unchanged.

## 0.1.0-alpha — 2026-08-17
- Initial architecture scaffold.

## 0.1.0-alpha3 - 2026-08-17
- Add system/course shared access-key requirement for temporary-user entry; secrets are hash-only.
