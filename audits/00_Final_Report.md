# Deep Audit — Final Report

**Projekt:** myimouto (PHP Imageboard, Moebooru/Danbooru-style)
**Datum:** 2026-04-09
**Scope:** 202 PHP-Dateien, 43 Controller, 53 Models, 38 Migrationen, 43 JS-Dateien

---

## Executive Summary

myimouto ist ein aktiv gepflegtes PHP-Imageboard auf einem proprietären, nicht mehr gewarteten Rails-inspirierten Framework. Die jüngste Modernisierungswelle (PROJ-9 bis PROJ-45) hat substantielle Security-Hardening-, Moderations- und Compliance-Features geliefert. Die Kernschwächen liegen in der veralteten Frontend-Architektur (duales JS-Framework mit 760 KB synchron geladener Payload), lückenhafter Testabdeckung (8 Testdateien für 43 Controller + 53 Models), mehreren offenen Security-Findings (SHA-1 Dual-Write, deaktivierte TLS-Verifizierung, unauthentifizierter Log-Endpunkt) und einem schema.sql-Drift, der Neuinstallationen bricht. Der Deployment-Prozess ist solide (Release-Directories, Rollback-Skript), aber CI-Quality-Gates (PHPStan, CS-Fixer) werden still übersprungen.

---

## Project Risk Level: Medium-High

**Begründung:** 6 High-Severity Security-Findings mit bekannten Exploit-Szenarien, kombiniert mit minimaler Testabdeckung und einem Framework ohne Security-Advisory-Channel. Die jüngste Hardening-Arbeit (CSRF, Rate-Limiting, bcrypt-Migration) zeigt Problembewusstsein, aber Legacy-Altlasten (SHA-1, TLS-Skip, XSS in Views) sind noch nicht vollständig behoben. Für eine öffentlich erreichbare Multi-User-Plattform mit Datei-Uploads ist das Risikoprofil ernst zu nehmen.

---

## Agent Scores Overview

| # | Agent | Score | Kernbefund |
|---|-------|-------|------------|
| 1 | Architecture | 5/10 | Proprietäres Framework, God-Object Post-Model, keine Namespaces, 8 Tests |
| 2 | Frontend | 4/10 | Dual-JS-Framework (760 KB), keine Responsive Breakpoints, Prototype.js Legacy |
| 3 | Backend | 6/10 | Gute Security-Basis (CSRF, bcrypt, Rate-Limiting), aber SHA-1 Dual-Write, fehlende Transaktionen |
| 4 | Database | 5.5/10 | schema.sql 15 Tabellen veraltet, ORDER BY RAND(), non-sargable Queries |
| 5 | Infrastructure | 6/10 | Solides Deployment, aber Nginx-Config fehlt, PHPStan/CS-Fixer deaktiviert |
| 6 | Security | 6.5/10 | Bewusste Härtung, aber 6 High-Findings offen (SHA-1, TLS, XSS, SSRF, Log-Injection) |

**Durchschnitt: 5.5/10**

---

## Top 10 Prioritized Actions

### 1. SHA-1 Dual-Write stoppen + Default-Salt-Prüfung
- **Problem:** `User::_encrypt_password()` schreibt SHA-1 UND bcrypt bei jedem Passwortwechsel. Default-Salt `choujin-steiner` ist öffentlich bekannt.
- **Impact:** Datenbankdump → alle SHA-1 Hashes in Minuten knackbar mit vorberechneten Rainbow-Tables.
- **Lösung:** SHA-1-Schreibpfad entfernen. Boot-Check: Exception wenn Default-Salt in Produktion aktiv.
- **Aufwand:** S
- **Agents:** Security F-05, Backend F-01, Architecture #14, Infrastructure #7

### 2. Unauthentifizierter Log-Write-Endpunkt absichern
- **Problem:** `UserController::error()` und `PostController::error()` akzeptieren beliebig große POST-Payloads ohne Auth und schreiben direkt auf Disk.
- **Impact:** Disk-Exhaustion DoS, Log-Injection, Log-Parser-Exploitation.
- **Lösung:** `member_only` Filter, 2 KB Payload-Limit, Control-Character-Sanitization.
- **Aufwand:** S
- **Agents:** Security F-01, Backend F-05

### 3. TLS-Zertifikatsverifizierung reaktivieren
- **Problem:** `Danbooru::http_get_streaming()` setzt `CURLOPT_SSL_VERIFYPEER = false` und `CURLOPT_SSL_VERIFYHOST = false`.
- **Impact:** Man-in-the-Middle bei allen HTTPS Source-Downloads (Post-Uploads von URL).
- **Lösung:** Beide Optionen entfernen. `CURLOPT_CAINFO` auf System-CA-Bundle setzen.
- **Aufwand:** S
- **Agents:** Security F-04

### 4. Stored XSS in Ban-Reason escapen
- **Problem:** `banned/index.php` gibt `$this->ban->reason` unescaped aus. Kompromittierter Mod kann JavaScript injizieren.
- **Impact:** Session-Cookie-Exfiltration bei jedem gebannten User.
- **Lösung:** `<?= $this->h($this->ban->reason) ?>`. Systematisches View-Audit für unescapte Model-Ausgaben.
- **Aufwand:** S
- **Agents:** Security F-06

### 5. SQL-Injection-Risiko in updateBatch parameterisieren
- **Problem:** `PostController::updateBatch()` interpoliert IDs direkt in `Post::where("id IN ($ids)")`.
- **Impact:** Fragile Vertrauenskette — Refactoring-Regression koennte echte Injection eroeffnen.
- **Lösung:** `array_map('intval', $ids)` oder ORM-Array-Binding.
- **Aufwand:** S
- **Agents:** Security F-02, Backend F-04

### 6. SSRF via data:-URL Bypass entfernen
- **Problem:** `Danbooru::http_get_streaming()` dekodiert `data:`-URLs vor der SSRF-Prüfung.
- **Impact:** Upload-Whitelist und SSRF-Guards werden umgangen.
- **Lösung:** `data:`-URL-Shortcut entfernen oder URL-Schema-Validierung vor den Branch verschieben.
- **Aufwand:** S
- **Agents:** Security F-03

### 7. blocked_only → member_only in DmailController
- **Problem:** `blocked_only` erlaubt Level 10 (Blocked), d.h. gebannte User koennen weiterhin DMs senden.
- **Impact:** Moderations-Intent wird unterlaufen.
- **Lösung:** Filter auf `member_only` (Level >= 20) aendern.
- **Aufwand:** S
- **Agents:** Backend F-03

### 8. CRC32 Session-Token durch SHA-256 ersetzen
- **Problem:** Session-Invalidierung nutzt `crc32($bcrypt_hash)` — 32-Bit Kollisionsraum.
- **Impact:** Theoretischer Bypass der Passwort-Änderungs-Invalidierung.
- **Lösung:** `substr(hash('sha256', $bcrypt_hash), 0, 16)`.
- **Aufwand:** S
- **Agents:** Backend F-02, Security F-09

### 9. PHPStan + PHP-CS-Fixer Konfiguration committen
- **Problem:** CI-Quality-Gates werden still übersprungen (exit 0 bei fehlender Config).
- **Impact:** Statische Analyse und Code-Style faktisch deaktiviert.
- **Lösung:** `phpstan.neon.dist` (Level 3-5) und `.php-cs-fixer.dist.php` (PSR-12) committen.
- **Aufwand:** S
- **Agents:** Infrastructure #3

### 10. schema.sql regenerieren
- **Problem:** 15 Tabellen aus 2026er-Migrationen fehlen in `schema.sql`. Neuinstallation bricht.
- **Impact:** Onboarding unmoeglich ohne manuelle Migration. CI kann Schema nicht validieren.
- **Lösung:** `mysqldump --no-data` nach vollständiger Migration. Als CI-Step automatisieren.
- **Aufwand:** M
- **Agents:** Database #1

---

## Estimated Total Effort

| Aufwand | Anzahl Actions | Gesamt |
|---------|---------------|--------|
| S (< 1 Tag) | 8 | ~6 Personentage |
| M (1-3 Tage) | 2 | ~4 Personentage |
| **Gesamt Top 10** | | **~10 Personentage** |

Vollstaendiges Audit-Backlog (alle Findings aller Agents): ~45-60 Personentage, davon ~20 Personentage für Frontend-Modernisierung (Prototype.js-Migration, Responsive Design).

---

## Recommended Implementation Order

### Phase 1 — Security Critical (Woche 1-2)
1. SHA-1 Dual-Write stoppen + Default-Salt-Check
2. TLS-Verifizierung reaktivieren
3. Unauthentifizierter Log-Endpunkt absichern
4. XSS in Ban-Reason escapen + View-Audit starten
5. SQL-Injection in updateBatch parameterisieren
6. data:-URL SSRF-Bypass entfernen
7. blocked_only → member_only in DmailController
8. CRC32 → SHA-256 Session-Token

### Phase 2 — Quality Gates (Woche 3)
9. PHPStan + CS-Fixer Config committen
10. schema.sql regenerieren
11. Asset-Build in CI integrieren
12. install.php: bcrypt-Hash für Admin-Account
13. Composer audit in CI

### Phase 3 — Infrastructure (Woche 4-6)
14. Nginx-Konfiguration ins Repository
15. serve_static_assets = false
16. IE8.js + IE Conditional Comments entfernen
17. Healthcheck-Endpunkt implementieren
18. Open-Redirect in access_denied() fixen

### Phase 4 — Frontend Performance (Monat 2-3)
19. JS-Includes auf `defer` umstellen
20. aria-expanded in menu.js dynamisieren
21. robots.txt + Meta-Description fixen
22. Prototype.js → jQuery Migration starten
23. Responsive Breakpoints einfuehren

### Phase 5 — Database Optimization (Monat 3-4)
24. change_seq Spalte hinzufuegen oder entfernen
25. ORDER BY RAND() ersetzen
26. Tag-Implication-Resolution auf rekursive CTE umstellen
27. Non-sargable LOWER(source) LIKE fixen
28. Kommentierte Transaktionen reaktivieren

### Phase 6 — Architecture (Monat 4+)
29. Test-Coverage auf 40% für app/models/ steigern
30. PostController in Service-Objects extrahieren
31. User-Model Trait-Decomposition
32. strict_types schrittweise einfuehren
33. Zend-Mail Shim sunset

---

## Security Heatmap

```
Risiko-Verteilung nach Komponente:

                    Kritisch   Hoch    Mittel   Niedrig
                    --------   ----    ------   -------
Auth/Session        [##]       [#]     [##]     [#]
  SHA-1 dual-write, CRC32 token, install.php SHA-1 only

File Upload/SSRF    [#]        [##]    [#]
  data: bypass, TLS disabled, DNS rebinding, MIME detection

Views/XSS           [#]        [#]     [#]
  ban reason unescaped, unsafe-inline CSP, return_to (fixed)

SQL/Data            [#]        [##]    [#]
  updateBatch IN(), schema drift, missing transactions

Endpoints           [#]        [#]
  unauthenticated log write, history without auth

Infrastructure                 [##]    [##]
  PHPStan deaktiviert, Nginx-Config fehlt, kein CD
```

**Höchstes Risiko:** Auth/Session-Cluster (SHA-1 + CRC32 + bekannter Salt) — ein einzelner Datenbankdump kompromittiert alle Legacy-Passwoerter.

**Größte Angriffsfläche:** File Upload/SSRF — vier separate Findings in der Upload-Pipeline, von denen drei mit S-Aufwand fixbar sind.

---

## Agent-Empfehlungen für Remediation

| Bereich | Empfohlener Agent |
|---------|-------------------|
| Frontend-Fixes (a11y, responsive, JS-Migration) | `frontend-designer` — UI-Fixes mit integrierter Guideline-Prüfung |
| Dokumentationslücken (SECURITY.md, CHANGELOG, API-Docs) | `code-documenter` — README, API-Docs, Inline-Kommentare |
| Code-Qualität / Refactoring (Fat Controller, God Object) | `code-improver` — Lesbarkeit, Performance, Best Practices |

---

```
--- Tool Usage ---
Agent(audit-architecture): launched, score 5/10
Agent(audit-frontend): launched, score 4/10
Agent(audit-backend): launched, score 6/10
Agent(audit-database): launched, score 5.5/10
Agent(audit-infrastructure): launched, score 6/10
Agent(audit-security): launched, score 6.5/10
Write: 7 calls (audits/00-06)
Read: 6 calls (agent output files)
---
```
