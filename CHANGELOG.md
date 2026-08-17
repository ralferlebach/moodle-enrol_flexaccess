# Changelog

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
