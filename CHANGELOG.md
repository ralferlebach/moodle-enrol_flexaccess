# Changelog

## 0.1.35 — 2026-08-19 — DSGVO-Privacy-Provider (§11) + PHPDoc-Fixes
- **PHPDoc-Fix:** fehlender `@param $clientip` bei `access_controller::grant_quick_registration` (CI-PHPDoc-Checker).
- Privacy bleibt `null_provider` (keine eigenen personenbezogenen Tabellen; Einschreibungen liegen in den Kern-Tabellen).

## 0.1.35 — 2026-08-19 — DSGVO-Datenschutz-Provider vervollstaendigt (§11)
- Keine Codeaenderung (Provider bleibt korrekt `null_provider`: `enrol_flexaccess_instance` hat keine userid; Einschreibungen liegen in Core-Tabellen).

## 0.1.34 — 2026-08-19 — Rate-Limiting der oeffentlichen Schreib-Endpoints (§5)
- **Quick-Registration rate-limitiert** pro Client-Adresse (NAT-freundlich: 30/10min, damit eine ganze Klasse hinter einer IP nicht blockiert wird, Skript-Massenanlage aber gebremst). `grant_quick_registration()` nimmt die Client-Adresse und liefert `ratelimited`; `register.php` reicht `getremoteaddr()` durch. Neuer String `access:ratelimited`.
- Tests: `rate_limiter_test` (Sliding-Window + Magic-pro-E-Mail), Quick-Reg-IP-Limit-Integrationstest.

## 0.1.33 — 2026-08-19 — Enrolment-Expiry (§32/§33) + echte jmeter/playwright-Plaene (§26/§27)
- **Enrolment-Expiry umgesetzt** (Review §33): neuer Service `local\enrol_expiry::process()`, den der Task `expire_enrolments` nun aufruft (war leer). Aktive FlexAccess-Enrolments mit abgelaufenem `timeend` werden je nach Instanz-Policy **suspendiert oder ausgetragen**. Getestet: suspend, unenrol, aktive/unbefristete bleiben unberuehrt.
- **Einschreibungsdauer konfigurierbar** (Review §32): `enrolperiod` wird jetzt im Instanzformular angeboten (Dauer, optional) und in `instance_config::save()` persistiert; `enrol_with_capacity()` setzt daraus das `timeend`. Test: gespeichert + angewandt.
- **Lasttest realistisch** (Review §26): JMX um eine Write-Thread-Gruppe erweitert, die anonyme temporaere Nutzer real per POST/Confirm erzeugt (Cookie-Manager + sesskey-Extraktion), zusaetzlich zu den Read-Endpoints.
- **Browser-Journeys** (Review §27): Playwright deckt jetzt den anonymen Temporary-Entry, die Schnellregistrierung mit Re-Login und die Persistierung temporaer->dauerhaft mit Re-Login ab; `seed.php` richtet den Kurs entsprechend ein.

## 0.1.32 — 2026-08-19 — Magic-Login, Mail-Queue-Retrofit, SEC-03, main-CI + jmeter/playwright
- Tests: `magic_login_test` (5), `persistence_test` um SEC-03-Fall erweitert (abgelaufenes Konto nicht reaktivierbar) und auf den Queue-Versand umgestellt.
- **Neue main-CI** `.github/workflows/main.yml` (push/PR nach main): plugin-uebergreifend PHPUnit+Behat+phpcs (alle 4 via --extra-plugins), plus echte **jmeter**- und **playwright**-Jobs gegen eine installierte Site.
- **JMeter** trifft jetzt echte FlexAccess-Endpoints (`access.php` mit Policy-Aufloesung, `magic.php`) statt nur `/login` (Review §26), mit 200-/Exception-/Latenz-Assertions. **Playwright** um anonymen Temporary-Entry-Flow + Magic-Render erweitert (Review §27). Neue Fixtures `tests/fixtures/setup_load_course.php` und erweitertes `tests/playwright/seed.php`.

## 0.1.31 — 2026-08-18 — Aufraeumen: toter persistence_followup-Mailpfad entfernt
- `access_controller::grant_temporary_access()` plant keinen Persistenz-Followup mehr ein (Konstante `FOLLOWUP_AFTER` entfernt). Test entsprechend angepasst.

## 0.1.30 — 2026-08-18 — DRY: gemeinsame Identitaetsfelder der Formulare
- Keine Codeaenderung.

## 0.1.29 — 2026-08-18 — Paket B: E-Mail-Verifikation der Persistierung (Option, Default an)
- Tests in `persistence_test` ergaenzt: mit Verifikation wird eine Mail mit Einmal-Token versendet, das Konto bleibt bis zur Bestaetigung temporaer/nicht anmeldbar; nach `confirm_persistence` ist es dauerhaft, weiterhin eingeschrieben und anmeldbar; Link ist nicht wiederverwendbar. Ohne Verifikation konvertiert es sofort.

## 0.1.28 — 2026-08-18 — Paket B: B4 Konvertierung temporaer -> persistent
- Test `persistence_test`: temporaerer Zugang -> Persistierung -> gleiche user id weiterhin eingeschrieben UND per E-Mail+Passwort anmeldbar; Persistierung wird fuer Nicht-temporaere Konten abgelehnt.

## 0.1.27 — 2026-08-18 — Paket A abgeschlossen: Methodenauswahl (Gast + Normallogin)
- Neue Facades `api::offers_guest_access()` (fenstergebunden) und `api::offers_normal_login()` (nicht fenstergebunden, da Login-Fallback). Test in `access_entry_test` ergaenzt.

## 0.1.26 — 2026-08-18 — Paket A: Quick-Registration (allowquick)
- **Grant-Pfad fuer Schnellregistrierung**: `access_controller::grant_quick_registration()` prueft Fenster/allowquick/Instanz/Kapazitaet, erzeugt das persistente Konto und schreibt es ein. Neuer Facade `api::offers_quick_registration()`. Test `quick_registration_test` beweist: erzeugtes Konto ist eingeschrieben UND kann sich mit E-Mail+Passwort erneut anmelden (`user_login`).

## 0.1.25 — 2026-08-18 — CI-Fix (veraltete Behat-Datei)
- **`tests/behat/temporary_access_key.feature` als funktionierender Test neu angelegt** (statt der in 0.1.20 geloeschten Version mit undefinierten Steps). Grund: Beim Entpacken eines Zips ueber ein bestehendes Repo werden geloeschte Dateien nicht entfernt, sodass die Altdatei in der CI weiter lief. Die neue Datei nutzt die vorhandenen Steps und prueft die Access-Key-Challenge (falscher Schluessel abgewiesen, korrekter gewaehrt Zugang). Lokal verifiziert: 1 Szenario, 10 Steps gruen.

## 0.1.24 — 2026-08-18 — Paket A: B2 (Access-Key) verifiziert
- **Access-Key-Durchsetzung end-to-end per Behat verifiziert** (Sicherheits-Blocker B2 geschlossen): Challenge-Formular, falscher Schluessel wird abgewiesen, korrekter Schluessel gewaehrt Zugang; Rate-Limit im Flow, Schluessel nur per POST (nie in URL/Log). 3 Ecosystem-Szenarien, 20 Steps gruen.
- Neuer Facade `api::requires_temporary_access_key()` (kapselt die Key-Pflicht der Policy fuer den Entry-Point).

## 0.1.23 — 2026-08-18 — CI-Fixes
- **Behat-Fix:** `can_hide_show_instance()` ueberschrieben. Ohne diese Methode meldete Moodle auf der Seite „Einschreibemethoden verwalten" ein `debugging()` ("should override can_hide_show_instance()"), was den @javascript-Behat-Lauf (`instance_access.feature`, "Add method") scheitern liess.
- Hinweis: `temporary_access_key.feature` (undefinierte Steps) wurde bereits in 0.1.20 entfernt; der rote CI-Lauf lief noch auf einem aelteren enrol-Commit — ein Push dieses Stands raeumt das auf.

## 0.1.23 — 2026-08-18 — Paket A (Access), Teil 2: Zugangsschlüssel
- **Der Zugangsschlüssel ist jetzt wirksam** (war Sicherheits-Blocker B2). E2E per Behat verifiziert: falscher Schlüssel -> Fehler, richtiger -> Kurszugang.
- **B2 (serverseitige Durchsetzung):** `access_controller::grant_temporary_access()` prueft den Schluessel jetzt VOR jeder Kontoerzeugung (neuer Status `badkey`); nicht umgehbar durch Direktaufruf. `access_key_service::verify()` loest System- bzw. Kurs-Hash intern auf und gibt nur einen Boolean zurueck.
- **Rate-Limit (B7-Teil):** neue Klasse `local\access_key_rate` (MUC-basiert, pro IP+Kurs, gleitendes Fenster) blockt Brute-Force nach 5 Fehlversuchen fuer 5 Minuten; der Schluessel selbst wird nie gespeichert/geloggt.
- **Konfigformular:** neuer Abschnitt „Zugangsschluessel" mit Modus (erben/kursspezifisch) und Schluesselfeld; beim Speichern wird ein neu eingegebener Schluessel gehasht (`password_hash`), ein leeres Feld laesst den bestehenden Hash unveraendert.
- Neue Tests `access_key_test` (Durchsetzung, Rate-Limiter, Formular-Hash-Roundtrip); neues Behat-Szenario Key-Gating.

## 0.1.22 — 2026-08-18 — Paket A (Access), Teil 1
- **Der URL-/aktivitaetssensitive Zugang funktioniert jetzt end-to-end** (war Beta-Blocker B1). Real per Behat verifiziert: ein anonymer Besucher gelangt ueber die Entry-Page zu temporaerem Zugang und landet im Zielkurs.
- **B3 (Konfigformular):** das Enrolment-Formular exponiert und speichert jetzt die zentralen P0-Felder — `allowtemporary`, `allowquick`, `allowguest`, `allownormallogin`, `temporarylifetime`, `expiryaction` (zuvor nur gespeichert/Default). `instance_config::save()` persistiert sie.
- **Eine FlexAccess-Instanz pro Kurs** (`can_add_instance`) — beseitigt Policy-/Capacity-Ambiguitaet (Review §15).
- **Neuer Facade `api::offers_anonymous_entry()`** (Methode aktiv + Fenster offen + mind. ein anonymer Modus). Neue Tests `access_entry_test` (offers/Fenster/Persistenz/Eine-Instanz).
- **Ecosystem-Behat erweitert:** neues Szenario „anonymer Deep-Link-Entry" + Steps; lokal gruen.

## 0.1.21 — 2026-08-18
- **Cross-Plugin-Funktionalitaet wird jetzt echt end-to-end getestet.** Behat wurde in der Sandbox real ausgefuehrt (Moodle 5.3dev, non-JS): alle vier Standalone-Smoke-Features **und** ein neues Cross-Plugin-E2E-Szenario bestehen.
- **Ecosystem-E2E-Behat:** neues `tests/behat/ecosystem.feature` (Tag `@flexaccess_ecosystem`) mit Step-Definition `tests/behat/behat_enrol_flexaccess.php`. Es treibt den **realen** Enrol-Grant-Flow (`access_controller::grant_temporary_access`, der ueber `auth_flexaccess` ein temporaeres Konto erzeugt und einschreibt) und prueft, dass das `tool_flexaccess`-Dashboard den Account zaehlt — also auth+enrol+tool in einem Test. Lokal verifiziert: 1 Szenario, 6 Steps gruen.
- **Ecosystem-CI:** die phpunit- und behat-Jobs installieren die Schwester-Plugins via `moodle-plugin-ci --extra-plugins` (Checkout von auth/mod/tool). Damit laufen die Cross-Plugin-PHPUnit-Tests in der CI (statt sich zu ueberspringen) und die Vier-Plugin-Installation inkl. der zyklischen auth<->enrol-Abhaengigkeit wird bei jedem Lauf frisch validiert (Review §21/Paket F).

## 0.1.20 — 2026-08-18
- **Behat gruen gemacht (war der letzte rote CI-Schritt).** Die Feature-Dateien testeten teils veraltetes Scaffold-Verhalten bzw. noch nicht implementierte Ablaeufe; sie wurden auf standalone lauffaehige Smoke-Szenarien mit ausschliesslich Standard-Steps umgestellt. Verifiziert mit moodle-plugin-ci 4.5.11 (phpcs 0/0, validate 0 Fehler, PHPUnit auf Moodle 5.3dev gruen).
- **Playwright- und jMeter-Lasttests real implementiert** (`tests/playwright/`, `tests/load/`): lauffaehige Browser-Smoke-Tests (Admin-Login -> FlexAccess-Enrolment gelistet) und ein JMeter-Plateau-Lastplan auf einen Read-Endpoint, jeweils per `make playwright` / `make jmeter` startbar und ueber die Workflows automatisiert. `load.yml` von vimipad-Resten (workspaceid/cmid) bereinigt; phpcs schliesst `tests/{playwright,load}` aus. Behat `settings.feature` unveraendert lauffaehig.

## 0.1.19 — 2026-08-18
- **Verifiziert mit der exakten CI-Toolchain (moodle-plugin-ci 4.5.11 PHAR): phpcs 0/0, `validate` 0 Fehler, PHPUnit auf Moodle 5.3dev gruen.** Cross-Plugin-Integrationstests laufen in der Vollumgebung (alle vier Plugins) normal und ueberspringen sich nur in der Einzel-Plugin-CI.
- **Weitere CI-Fixes:** PHPDoc `incomplete parameters list` in Test-Helfern behoben (`access_gate_test::policy`, `restriction_evaluator_test::rule`, `restriction_service_test::restrict` mit vollstaendigen `@param`/`@return`). `access_controller_test` ueberspringt sich sauber (markTestSkipped), wenn das Schwester-Plugin `auth_flexaccess` in der Einzel-Plugin-CI nicht installiert ist. Behat `settings.feature` mit `@enrol`-Typ-Tag.

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
