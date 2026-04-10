# Assets & Infrastructure Audit

**Projekt:** myimouto (PHP Imageboard)
**Auditiert:** 2026-04-09
**Basis:** `D:/repos/myimouto/myimouto`

---

## Overview

| Dimension | Wert |
|---|---|
| Build-Tool | Custom PHP Asset Pipeline (RailsPHP `assets compile:all`) + Node.js (terser, clean-css-cli) |
| Hosting | VPS (Ubuntu 24.04), Nginx + PHP 8.5-FPM |
| Webserver | Apache-kompatibel (.htaccess), produktiv hinter Nginx (kein Nginx-Config im Repo) |
| CDN | Keines konfiguriert |
| Deployment | Release-Directory-Strategie via `scripts/deploy.sh` (Symlink, Smoke-Test, Auto-Rollback) |
| CI/CD | GitHub Actions (`.github/workflows/php.yml`), manueller Deploy-Trigger (kein CD) |
| Asset-Bundles | 3 Bundles: `application.js` (375 KB raw), `moe-legacy/application.js` (376 KB raw), `application.css` (56 KB raw) |
| Komprimierung | Gzip (Level 9) + Brotli (`--best`) vorausgerechnet, pre-komprimierte Dateien vorhanden |

---

## Quick Wins

1. **Nginx Security-Header-Block hinzufuegen** (kein Apache auf Prod): Die `.htaccess`-Header-Direktiven werden unter Nginx ignoriert. `add_header` im Nginx-Server-Block erganzen. Aufwand: S.
2. **`X-XSS-Protection` Header entfernen**: Der aktuell nicht gesetzte Header ist veraltet und koennte mit `0` explizit deaktiviert werden, um Browser-XSS-Auditor-Bugs zu verhindern. Aufwand: S.
3. **`IE8.js` (93 KB) aus dem Layout entfernen**: Die Datei wird in `app/views/layouts/default.php` geladen und `app/views/layouts/application.php`. IE8 hat unter 0,01% Marktanteil und jeder noch vorhandene Nutzer ist laengst nicht mehr supportet. Die unkomprimierte Datei kostet jeden Seitenaufruf 93 KB (ungezippt). Aufwand: S.
4. **PHPStan- und PHP-CS-Fixer-Konfigurationsdateien committen**: Beide CI-Skripte prufen auf `phpstan.neon`/`.php-cs-fixer.dist.php` -- diese Dateien existieren nicht im Repository. Die `analyse`- und `cs-check`-Steps in der CI werden still uebersprungen (`exit(0)` bei fehlendem Config). Aufwand: S.
5. **Standardpasswort-Salt in `default_config.php` aendern**: `user_password_salt = 'choujin-steiner'` ist ein bekannter Klartext-Wert aus dem Original-Moebooru-Repo. Jede Installation ohne Override in `config/config.php` nutzt diesen Wert. Aufwand: S.

---

## Findings

### 1. Nginx-Konfiguration fuer Assets fehlt im Repository

- **Prioritaet:** High
- **Problem:** Die Caching- und Compression-Logik in `public/.htaccess` setzt Apache-Module voraus (`mod_headers`, `mod_rewrite`). Die Produktionsumgebung ist laut `docs/SERVER_SETUP_UBUNTU_24_04_PHP85.md` Nginx. Nginx ignoriert `.htaccess` vollstaendig. Die in der `.htaccess` definierten `Cache-Control: public, max-age=31536000, immutable`-Header fuer Digest-Assets und die Pre-Compression-Rewrite-Logik (Brotli, Gzip) werden auf dem Produktionsserver nicht ausgefuehrt.
- **Impact:** Alle Assets werden ohne Cache-Control-Header ausgeliefert. Browser cachen nicht. Keine Pre-compressed-Asset-Auslieferung. Saemtliche Arbeit aus PROJ-32 (Cache-Headers, Brotli-Vorkomprimierung) ist auf dem Nginx-VPS wirkungslos.
- **Solution:** Einen Nginx-Server-Block-Snippet (`nginx/myimouto.conf`) in das Repository aufnehmen, der die `location /assets/` mit `expires 1y`, `add_header Cache-Control "public, immutable"`, `gzip_static on` und `brotli_static on` konfiguriert. Dieser Snippet sollte in `docs/SERVER_SETUP_UBUNTU_24_04_PHP85.md` referenziert und bei jedem Deployment aktiv gehalten werden.
- **Effort:** M

### 2. Asset-Build-Schritt fehlt in der CI-Pipeline

- **Prioritaet:** High
- **Problem:** `.github/workflows/php.yml` fuehrt keinen `composer run assets:build` Schritt durch. Es gibt weder npm-Installation noch einen Asset-Compile-Schritt. Der CI-Job prueft nur PHP-Linting, PHPUnit, PHPStan, und PHP-CS-Fixer. Die kompilierten Assets (`public/assets/`) sind laut `.gitignore` aus dem Repository ausgeschlossen (`/public/assets`). Damit existiert kein automatisierter Beweis, dass die Assets fehlerfrei gebaut werden koennen.
- **Impact:** Ein kaputter Asset-Build wird nicht in CI erkannt, sondern erst beim ersten Produktions-Deploy. Minifizierungs-Fehler (z.B. JS-Syntax-Fehler durch Terser) sind nicht CI-blockierend.
- **Solution:** CI um einen `assets:build`-Job erweitern: Node.js installieren (22.x LTS), `npm ci` ausfuehren, `composer run assets:build` ausfuehren. `public/assets/` als Build-Artefakt archivieren (optional). Gibt es einen Minifizierungsfehler, schlaegt der CI-Build fehl.
- **Effort:** M

### 3. PHPStan und PHP-CS-Fixer werden in CI still uebersprungen

- **Prioritaet:** High
- **Problem:** `script/ci/phpstan.php` und `script/ci/php_cs_fixer_check.php` pruefen beide, ob eine Konfigurationsdatei (`phpstan.neon`/`phpstan.neon.dist` bzw. `.php-cs-fixer.dist.php`) vorhanden ist. Ist sie nicht vorhanden, wird `exit(0)` zurueckgegeben -- CI-gruen, keine Pruefung. In diesem Repository existieren weder `phpstan.neon` noch `.php-cs-fixer.dist.php` im Root-Verzeichnis. Die `analyse`- und `cs-check`-CI-Steps geben daher immer `0` zurueck, unabhaengig vom Codestand.
- **Impact:** Statische Analyse und Code-Style-Pruefung sind de facto deaktiviert. Qualitaets-Gates funktionieren nicht. Der CLAUDE.md gibt an "quality gates are target-state requirements for modernization" -- aber das aktuelle Setup gibt keinen Fehler, wenn die Gates fehlen.
- **Solution:** `phpstan.neon.dist` (minimale Konfiguration: Level 3-5, Scan von `app/`, `lib/`) und `.php-cs-fixer.dist.php` (PSR-12-Basis oder PER-CS) committen. Alternativ die CI-Wrapper-Skripte so aendern, dass sie mit einem klaren Fehler (`exit(1)`) abbrechen, wenn die Konfiguration fehlt, anstatt still zu ueberspringen.
- **Effort:** S

### 4. Kein dedizierter Healthcheck-Endpunkt fuer Deployments

- **Prioritaet:** Medium
- **Problem:** Der Smoke-Test in `scripts/deploy.sh` und `scripts/rollback.sh` prueft `GET /post` und akzeptiert HTTP 200, 301, und 302 als Erfolg. `/post` loest eine echte Datenbankabfrage aus (Posts-Listing), beinhaltet Authentifizierungspruefungen und rendert ein Template. Ein Datenbankfehler kann trotzdem 302 zurueckgeben (Redirect zum Login). Ein echter HTTP-5xx-Fehler wuerde erst nach dem Symlink-Umschalten zu einem Rollback fuehren -- aber der Smoke-Test nutzt bereits die neue Release.
- **Impact:** Ein kaputter Release koennte den Smoke-Test bestehen (wenn `/post` auf eine Login-Seite redirected, HTTP 302) obwohl die Datenbank nicht erreichbar ist oder Migrations fehlgeschlagen sind. Der Rollback-Mechanismus greift dann korrekt, aber erst nach kurzem Live-Traffic auf dem kaputten Release.
- **Solution:** Einen einfachen `GET /healthz`-Endpunkt implementieren, der keine Datenbankabfrage macht (nur PHP-Bootstrap prueft) und HTTP 200 mit `{"status":"ok"}` antwortet. Oder: einen Endpunkt der eine triviale DB-Query ausfuehrt (`SELECT 1`) und explizit fehlschlaegt. Den Smoke-Test auf diesen Endpunkt umstellen, 302 nicht als Erfolg werten.
- **Effort:** S

### 5. CSP nutzt `unsafe-inline` fuer Scripts und Styles

- **Prioritaet:** Medium
- **Problem:** Die Content-Security-Policy in `ApplicationController.php` (Zeile 627) ist: `"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'self'"`. `unsafe-inline` erlaubt alle Inline-Scripts und Inline-Styles -- das negiert den Grossteil des XSS-Schutzes durch CSP.
- **Impact:** Ein XSS-Angriff kann beliebige Inline-Scripts einschleusen, ohne durch die CSP geblockt zu werden. Die CSP bietet in dieser Konfiguration nur Schutz gegen das Laden externer Ressourcen von Drittdomains.
- **Solution:** Mittel- bis langfristig: Inline-Scripts aus den PHP-Views in externe Dateien auslagern. Dann `unsafe-inline` entfernen. Als Uebergangsloesung: Nonce-basierte CSP (`'nonce-{random}'`) implementieren, die nur explizit getaggte `<script nonce="...">`-Tags zulasst. Dies erfordert Aenderungen am View-Layer.
- **Effort:** L

### 6. Zwei JS-Bundles werden auf jeder Seite geladen (doppelter Transfer)

- **Prioritaet:** Medium
- **Problem:** `CONFIG()->asset_javascripts` definiert zwei Bundles: `application` (384 KB raw, 94 KB br) und `moe-legacy/application` (376 KB raw, 84 KB br). Laut Konfiguration und Manifest werden beide auf jeder Seite geladen. `moe-legacy/` enthaelt Prototype.js 1.7 und eine vollstaendige Legacy-Skript-Suite. Zusammen ergibt das 178 KB Brotli-Transfer fuer JavaScript allein -- plus 10 KB CSS.
- **Impact:** 188 KB Gesamttransfer (Brotli) fuer alle Assets. Das ist vertretbar, aber die Prototype.js-Abhaengigkeit in `moe-legacy/` deutet auf veraltete UI-Patterns hin. Werden beide Bundles auf Seiten geladen, die nur eines brauchen, wird der Browser unnoetig blockiert.
- **Solution:** Pruefen, welche Seiten tatsaechlich das `moe-legacy`-Bundle benoetigen (vermutlich Legacy-Browserkompatibilitaet oder Moebooru-spezifische Features). Lazy-Loading per `<script defer>` oder seitenspezifisches Laden implementieren. Langfristig: Prototype.js-Abhaengigkeiten auf jQuery migrieren (verknuepft mit PROJ-36 Framework-Migration).
- **Effort:** L

### 7. Standard-Salt fuer Passwort-Hashing ist ein bekannter Klartext-String

- **Prioritaet:** Medium
- **Problem:** `DefaultConfig::$user_password_salt = 'choujin-steiner'` in `config/default_config.php` ist ein bekannter Wert aus dem originalen Moebooru-Repository. Jede Installation, die diesen Wert in `config/config.php` nicht ueberschreibt, verwendet identische Salts fuer alle Passwort-Hashes. Der `config.php.example` ueberschreibt `user_password_salt` nicht.
- **Impact:** Bei einem Datenbankdump koennen Angreifer mit einem vorberechneten Rainbow-Table fuer `'choujin-steiner'` Passworthaeshes effizienter angreifen. [ANNAHME: das Hashing-Schema verwendet MD5 oder SHA1 mit diesem Salt, nicht bcrypt -- PROJ-27 beschreibt BCrypt-Migration als offenes Feature].
- **Solution:** `$user_password_salt` aus `config.php.example` in `LocalConfig` einbeziehen, mit einem Hinweis das dieser geaendert werden muss. Bei einer Neuinstallation sollte `install.php` einen zufaelligen Salt generieren und in `config/config.php` schreiben. PROJ-27 (BCrypt-Migration) als vorrangig markieren.
- **Effort:** S

### 8. HSTS wird nur gesendet wenn `url_base` mit `https://` beginnt -- keine Enforce-Logik

- **Prioritaet:** Medium
- **Problem:** `ApplicationController.php` sendet HSTS nur wenn `str_starts_with(CONFIG()->url_base, 'https://')`. Die Default-Konfiguration in `config.php.example` hat `url_base = 'http://127.0.0.1:3000'`. Vergisst ein Betreiber, die `url_base` auf HTTPS umzustellen (obwohl der Server HTTPS hat), wird kein HSTS-Header gesendet.
- **Impact:** Betreiber-Konfigurationsfehler kann dazu fuehren, dass HTTPS-Verbindungen nicht durch HSTS erzwungen werden, auch wenn Nginx/Let's Encrypt korrekt konfiguriert ist.
- **Solution:** Dokumentation in `config/config.php.example` erganzen: expliziter Hinweis, dass `url_base` bei HTTPS-Betrieb auf `https://` gesetzt werden muss. Alternativ: Hinweis in `install.php` oder Startup-Log.
- **Effort:** S

### 9. Keine automatisierte Sicherheitsueberpruefung der Composer-Abhaengigkeiten in CI

- **Prioritaet:** Medium
- **Problem:** Das CI-Workflow-File fuehrt kein `composer audit` aus. `docs/SERVER_SETUP_UBUNTU_24_04_PHP85.md` Abschnitt 11 (PROJ-15) empfiehlt `composer audit` nach Deploys manuell auszufuehren, aber dieser Schritt ist nicht automatisiert.
- **Impact:** Known-CVE-Pakete koennten durch einen `composer update` eingefuehrt werden, ohne dass CI anschlaegt.
- **Solution:** `composer audit --no-interaction` als CI-Step nach `composer install` hinzufuegen. Mit `continue-on-error: true` starten (informativ), dann nach Stabilisierung als blockierendes Gate einfuehren.
- **Effort:** S

### 10. `IE8.js` (93 KB unkomprimiert) wird aktiv im Layout geladen

- **Prioritaet:** Medium
- **Problem:** `app/views/layouts/default.php` (Zeile 40) und `app/views/layouts/application.php` enthalten `<script src="/IE8.js">`. Die Datei ist 92.6 KB unkomprimiert und liegt in `public/` ohne Digest-Hash -- sie bekommt also nur `Cache-Control: public, max-age=86400` (1 Tag), nicht den `immutable`-Cache. Da sie keinen Hash im Dateinamen hat, wird sie taeglich neu angefragt.
- **Impact:** 93 KB extra HTTP-Request auf jeder Seitenansicht fuer einen toten Browser. Kein Brotli/Gzip, da sie nicht Teil des Asset-Pipelines ist. Cache nur 1 Tag.
- **Solution:** `IE8.js` und alle Conditional-Comments `<!--[if lt IE 9]>` aus den Layouts entfernen.
- **Effort:** S

### 11. Kein CDN konfiguriert

- **Prioritaet:** Low
- **Problem:** Alle Assets (CSS, JS, Bilder, hochgeladene Dateien) werden direkt vom VPS-Origin ausgeliefert. Keine CDN-Integration ist vorhanden oder dokumentiert.
- **Impact:** Latenz ist standortabhaengig. Der Origin-Server traegt die gesamte Last des Asset-Transfers, auch fuer gecachte statische Dateien. Bei hohem Traffic (viele gleichzeitige Image-Downloads) ist der VPS Bandbreiten-Bottleneck.
- **Solution:** Fuer eine Low-Traffic-Booru-Instanz ist kein CDN zwingend notwendig. Als skalierbare Option: Cloudflare Free Tier (Proxy-Modus) wuerde statische Assets cachen ohne Konfigurationsaenderungen im Code. Erfordert DNS-Delegation. `CONFIG()->url_base` muss auf den oeffentlichen Hostnamen zeigen, nicht auf `127.0.0.1`.
- **Effort:** M

### 12. Test-Coverage ist minimal (8 Testdateien)

- **Prioritaet:** Low
- **Problem:** Das `tests/`-Verzeichnis enthaelt 8 PHP-Testdateien. Abgedeckt sind: PostReplacement (Staging, Apply), PostSearch API-Contract, Mail-Namespace, Post-API-Filter, TagHelper-Escaping. Grosse Bereiche (Controller-Auth, Upload-Pipeline, Tagging-Engine, Pools, Voting) sind nicht durch automatisierte Tests abgedeckt.
- **Impact:** Regressions in Core-Funktionen werden erst manuell im Browser erkannt. Entwickler-Experience leidet, weil Refactoring ohne Test-Netz riskant ist.
- **Solution:** Schrittweise Coverage aufbauen. Prioritaet: Controller-Integrationstests fuer auth-sensible Endpunkte, Datei-Upload-Logik, Tag-Parser.
- **Effort:** L

### 13. Legacy-Statik-Assets ohne WebP/AVIF-Alternativen

- **Prioritaet:** Low
- **Problem:** `public/images/` enthaelt PNG-Dateien mit erheblicher Groesse: `iphone-startup-ipad.png` (464 KB), `errors.png` (434 KB), `iphone-startup.png` (129 KB), `logo.png` (77 KB). Diese werden ohne Cache-Busting-Hash ausgeliefert (1 Tag Cache). Keine WebP- oder AVIF-Alternativen vorhanden.
- **Impact:** Legacy PNGs sind 3-5x groesser als aequivalente WebP-Dateien. `iphone-startup*.png` sind vermutlich Apple-Splash-Screen-Assets fuer ein altes iOS-Webapp-Feature und koennen moeglicherweise entfernt werden.
- **Solution:** `errors.png`, `logo.png` und andere regelmaessig geladene UI-Assets in WebP konvertieren (verlustfrei oder mit minimalem Qualitaetsverlust). `iphone-startup-ipad.png` und `iphone-startup.png` pruefen ob sie noch benoetigt werden.
- **Effort:** S

### 14. `serve_static_assets = true` in Produktion -- Nginx sollte das uebernehmen

- **Prioritaet:** Low
- **Problem:** `config/environments/production.php` setzt `$config->serve_static_assets = true`. Das bedeutet, statische Assets werden von PHP/RailsPHP ausgeliefert, nicht direkt von Nginx. Nginx sollte statische Dateien direkt ausliefern, ohne PHP-FPM zu bemuehen.
- **Impact:** Jeder Asset-Request durchlaeuft PHP-FPM (Bootstrap, Routing) anstatt direkt von Nginx gecacht geliefert zu werden. Das erhoet die PHP-FPM-Last und verlangsamt Asset-Auslieferung gegenueber direktem Nginx-Serving.
- **Solution:** Nginx-Konfiguration so einrichten, dass `location /assets/` direkt auf das Dateisystem zeigt (`root /home/vps/myimouto/public`), ohne PHP-FPM zu involvieren. Dann `serve_static_assets` auf `false` setzen. Dies ist Standard-Praxis und wird in der Server-Setup-Dokumentation nur teilweise adressiert.
- **Effort:** M

---

## Structural Improvements

### A. Nginx-Konfigurationsdatei ins Repository aufnehmen

Die vollstaendige Nginx-Site-Konfiguration (`nginx/myimouto.conf.example`) sollte im Repository versioniert sein. Derzeit enthaelt das Setup-Dokument nur einen Ein-Zeiler (`fastcgi_pass`). Ein vollstaendiger Block mit:
- `location /assets/` mit `expires 1y; add_header Cache-Control "public, immutable"; gzip_static on; brotli_static on;`
- `location /data/` fuer User-Uploads
- `location ~ \.php$` fuer PHP-FPM
- Rate-Limiting via `limit_req_zone` (ergaenzt die App-Level-Rate-Limiter)
- HTTP/2-Aktivierung
- TLS-Konfiguration-Hinweise

wuerde das Produktions-Setup reproduzierbar und reviewbar machen.

### B. CD-Pipeline implementieren (GitHub Actions Deployment)

Derzeit ist der Deployment-Prozess manuell: `./scripts/deploy.sh` wird lokal ausgefuehrt. Das fuehrt zu:
- "Works on my machine"-Risiko: Die Build-Umgebung des Entwicklers kann von der Produktionsumgebung abweichen
- Kein Audit-Trail fuer Deploys in GitHub

Empfehlung: GitHub Actions Workflow fuer automatisches Deployment auf den VPS nach erfolgreichem CI auf `master`. Voraussetzungen: SSH-Deploy-Key als GitHub Secret hinterlegen, `DEPLOY_HOST` als Repository-Variable setzen.

### C. PROJ-27 (BCrypt-Passwort-Migration) priorisieren

Das aktuelle Passwort-Hashing-Schema (Moebooru-Legacy) ist ein zentrales Sicherheitsrisiko. BCrypt-Migration blockiert keine laufenden Features und sollte als kritisch eingestuft werden. Der Aufwand ist M (Migration mit Lazy-Upgrade: beim naechsten Login auf BCrypt upgraden).

### D. Service Worker / Offline-Caching evaluieren

Fuer eine Image-Browsing-Anwendung koennte ein minimaler Service Worker die wahrgenommene Performance verbessern (Shell-Caching, Navigation-Preload). Dies ist allerdings nur sinnvoll nach Abschluss von PROJ-36 (Framework-Migration) oder als separates Frontend-Projekt.

---

## Performance Impact Estimate

| Massnahme | Erwartete Verbesserung |
|---|---|
| IE8.js entfernen | -93 KB pro Seitenaufruf (uncompressed), -1 HTTP-Request |
| Nginx Cache-Control fuer Assets | First-Repeat-Load: 0 KB Asset-Transfer (Disk-Cache) statt 188 KB |
| Asset-Build in CI | Keine User-Performance-Verbesserung, aber Deployments zuverlaessiger |
| Nginx direktes Asset-Serving (`serve_static_assets = false`) | Reduktion PHP-FPM-Last fuer statische Requests um ~100% |
| WebP fuer `errors.png` und `logo.png` | -350 KB fuer Error-Pages, -50 KB fuer Logo |
| CDN | Latenzreduktion um 50-200ms fuer geografisch entfernte Nutzer |

Hoechste Gesamtwirkung mit kleinstem Aufwand: IE8.js entfernen + Nginx-Config fuer Cache-Headers hinzufuegen + `serve_static_assets = false`. Zusammen eliminieren diese Massnahmen nahezu alle unnoetig grossen HTTP-Requests und PHP-FPM-Beteiligung an statischen Assets.

---

## Infrastructure Score: 6/10

**Begruendung:**

| Kategorie | Bewertung | Kommentar |
|---|---|---|
| Asset-Pipeline | 7/10 | Minifizierung, Brotli, Gzip, Digest-Hashing, Cleanup -- vollstaendig. Aber kein CI-Check. |
| Caching-Strategie | 4/10 | Korrekt in `.htaccess` definiert, aber unter Nginx wirkungslos. `serve_static_assets = true` inakzeptabel. |
| HTTP-Header / Security | 7/10 | X-Frame-Options, X-Content-Type-Options, HSTS (bedingt), CSP, Referrer-Policy vorhanden. CSP schwach (`unsafe-inline`). |
| CDN | 1/10 | Kein CDN. Nur VPS-Origin. |
| Build-Prozess | 6/10 | Reproducible, korrekte Toolchain, aber kein CI-Asset-Build. PHPStan/CS-Fixer-Config fehlen. |
| Deployment-Strategie | 8/10 | Release-Directory, Symlink-Swap, Rollback-Skript, Smoke-Test, 5 Releases vorhalten -- solide. Kein CD. |
| CI/CD | 5/10 | Lint, Tests, Coverage, Codecov. PHPStan/CS-Fixer werden still uebersprungen. Kein Asset-Build. Kein CD. |
| Test-Coverage | 3/10 | 8 Testdateien, nur PROJ-spezifische Bereiche. Kein Controller-Test, kein Upload-Test. |

Der Score reflektiert einen gut strukturierten, handwerklich soliden Deployment-Prozess und eine vollstaendige Asset-Pipeline-Implementierung (PROJ-32, PROJ-33 sind deployed), aber mit kritischen Luecken: das Fehlen einer Nginx-Konfiguration macht die PROJ-32-Cache-Arbeit auf dem Produktionsserver unwirksam, und die still deaktivierten Quality-Gates (PHPStan, CS-Fixer) erhoehen das Regressions-Risiko bei jeder Code-Aenderung.

---

**Relevante Dateipfade:**

- `D:/repos/myimouto/myimouto/public/.htaccess` -- Cache-Control und Compression-Regeln (Apache-only)
- `D:/repos/myimouto/myimouto/config/environments/production.php` -- `serve_static_assets = true` (Problem)
- `D:/repos/myimouto/myimouto/config/application.php` -- Asset-Compressor-Konfiguration
- `D:/repos/myimouto/myimouto/config/default_config.php:23` -- hardcodierter Passwort-Salt
- `D:/repos/myimouto/myimouto/app/controllers/ApplicationController.php:625-628` -- CSP mit `unsafe-inline`
- `D:/repos/myimouto/myimouto/.github/workflows/php.yml` -- CI-Pipeline (kein Asset-Build, kein CD)
- `D:/repos/myimouto/myimouto/scripts/deploy.sh` -- Release-Deploy-Skript (solide)
- `D:/repos/myimouto/myimouto/scripts/rollback.sh` -- Rollback-Skript
- `D:/repos/myimouto/myimouto/script/ci/phpstan.php:18-22` -- stille Ueberspringlogik (keine Config = exit 0)
- `D:/repos/myimouto/myimouto/script/ci/php_cs_fixer_check.php:19-23` -- stille Ueberspringlogik (keine Config = exit 0)
- `D:/repos/myimouto/myimouto/app/views/layouts/default.php:40` -- IE8.js-Ladung
- `D:/repos/myimouto/myimouto/public/assets/manifest.yml` -- Asset-Manifest (3 Eintraege)
- `D:/repos/myimouto/myimouto/docs/SERVER_SETUP_UBUNTU_24_04_PHP85.md` -- VPS-Setup-Dokumentation (kein vollstaendiger Nginx-Block)
