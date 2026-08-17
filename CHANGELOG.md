# Changelog

## 0.1.2 — 2026-08-17
- Scope-Erweiterung (Planung/Doku): **Kapazitätslimit** (aktive Einschreibungen, ADR-011) und **Access Window** (available from/until, kombinierbar mit Zugangsschlüssel, ADR-012) verbindlich aufgenommen. Siehe `../../docs/Arbeitsplanung.md`.

## 0.1.1 — 2026-08-17
- Version scheme moved to incremental `0.1.x` (release `0.1.1`).
- Dependency model documentation aligned to the ecosystem hard/cycle model (ADR-010); `enrol → auth` hard dependency unchanged.

## 0.1.0-alpha — 2026-08-17
- Initial architecture scaffold.

## 0.1.0-alpha3 - 2026-08-17
- Add system/course shared access-key requirement for temporary-user entry; secrets are hash-only.
