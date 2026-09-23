# Terravue (werknaam) — Travel Risk Monitor

Zelfstandige app van Peter Langerak (eigen onderneming). Gebruikers kiezen landen en zien per land het
officiële reisadvies en recent veiligheidsnieuws. Later: pushberichten als er iets verandert in de landen
die iemand volgt. Geleverd als **WordPress-plugin** op een eigen server, en installeerbaar als **PWA**.

Communicatie met Peter: **Nederlands**. UI: **Engels standaard**, met schakelaar EN / DE / NL.
Code en commentaar: Engels.

## Harde randvoorwaarden

- **Volledig los van SGL / Lely / elke werkgever.** Dit is een schone herbouw: geen code, teksten, data,
  namen, huisstijl of koppelingen (planning, SQL Server) van een werkgever gebruiken of overnemen.
  Waarschuw Peter direct als een wijziging daar toch richting gaat (werkgeversbeleid, IE, arbeidscontract).
- **AVG.** Persoonsgegevens beperkt tot: e-mailadres (WordPress-gebruiker), tijdstip van toestemming,
  gekozen landen, taal. Nieuwe persoonsgegevens (bv. push-abonnementen, locatie, telefoonnummer) alleen
  na expliciete afweging, met privacytekst, exporter/eraser en bewaartermijn. **Geen locatietracking.**
  Geen externe fonts/CDN's/analytics in de frontend (geen IP-lekken naar derden).
- **Registratie** is double opt-in via magic link; accounts met `edit_posts` (beheerders, redacteuren)
  kunnen nooit via een link inloggen.
- **Bronnen/licenties.** Reisadvies: BuZa open data (NL), FCDO via GOV.UK Content API (OGL v3, bronvermelding),
  Auswärtiges Amt open data (DE). Nieuws: GDELT (standaard). Google News RSS is alleen voor persoonlijk,
  niet-commercieel gebruik — voor een betaalde dienst een gelicentieerde nieuwsbron kiezen.
- **Merk.** "Terravue" is een werknaam; nog geen merkonderzoek gedaan. "UNECTA" is een bestaand merk van
  een Braziliaans bedrijf: niet gebruiken als naam, en de stijl niet zo dicht benaderen dat verwarring ontstaat.
- Geen build-stap, geen Composer/npm-dependencies in de plugin. PHP 8.0+, WordPress 6.4+.

## Structuur

```
travel-risk/                 De plugin (deze map wordt gezipt en geüpload)
  travel-risk.php            Bootstrap, instellingen, helpers (countries(), cached(), http_get())
  includes/Sources.php       Reisadvies-adapters BuZa / FCDO / AA -> niveau 1..4 (zonder WP, testbaar)
  includes/News.php          Nieuws (GDELT, Google News RSS), ruisfilter (zonder WP, testbaar)
  includes/Rest.php          REST API travel-risk/v1
  includes/Auth.php          Magic link / double opt-in, rate limiting
  includes/Pwa.php           Manifest + service worker via /?travel_risk_pwa=manifest|sw
  includes/Frontend.php      Shortcode [travel_risk], assets, config naar JS
  includes/Privacy.php       AVG: exporter, eraser, voorgestelde privacytekst
  includes/Admin.php         Instellingen > Travel Risk
  assets/app.js, app.css     Frontend (vanilla JS), vertalingen EN/DE/NL in app.js
  assets/sw.js               Service worker (alleen app-pagina, plugin-assets en eigen REST-routes)
  data/countries.json        ISO3/ISO2, namen en/de/nl, GOV.UK-slug
  tests/unit.php             Parsertests (php tests/unit.php)
  tests/wp-smoke.php         Integratietest in echte WP (wp eval-file ...)
  tests/fake-sources.php     Nepbronnen + mailvanger voor tests zonder internet
bin/build-zip.sh             Maakt dist/travel-risk-<versie>.zip (zonder tests)
```

## Risiconiveaus

1 groen = normale voorzorg, 2 geel = let op, 3 oranje = alleen noodzakelijke reizen, 4 rood = niet reizen.
`level` = grootste deel van het land, `maxLevel` = strengste niveau ergens in het land.
- FCDO: `details.alert_status` (whole_country / parts). Alleen regionale alerts -> level 2.
- AA: `warning` 4, `situationWarning` 3, `partialWarning` max 4, `situationPartWarning` max 3.
- BuZa: geen kleurveld; per zin geparsed (`Sources::buza_levels`). **Bij twijfel: testgeval toevoegen.**
- Taal kiest de bron: nl -> BuZa, en -> FCDO, de -> AA. Een bron geeft geen advies voor het eigen land.

## API (travel-risk/v1)

`GET advice/{ISO3}?lang=`, `GET news/{ISO3}`, `GET|PUT|DELETE me`, `POST login`, `POST login/verify`,
`POST logout`. Foutcodes als korte string (`rate_limited`, `not_found`, ...), vertaald in app.js.
Cache: transients, alleen succesvolle antwoorden, standaard 60 min. GDELT-aanroepen minimaal 6 s uit elkaar.

## Testen

```
php travel-risk/tests/unit.php
wp eval-file wp-content/plugins/travel-risk/tests/wp-smoke.php   # in een test-WordPress
```

## Backlog (voorstel)

1. **Pushberichten bij wijziging** (hoofddoel). WP-Cron (liefst echte cron) haalt periodiek het advies op
   voor alle gevolgde landen, bewaart per land/bron de laatste `level`/`maxLevel`/`updated` en stuurt bij
   wijziging een Web Push (VAPID) naar abonnees van dat land. Push-abonnementen zijn persoonsgegevens:
   aparte toestemming in de app, opslaan per gebruiker, verwijderen bij uitschrijven/account wissen,
   meenemen in exporter/eraser. iOS: alleen als de PWA op het beginscherm is gezet (iOS 16.4+).
   Terugvaloptie: e-mailnotificatie. Web Push-versleuteling vergt ECDH/HKDF/AES-GCM — met OpenSSL
   in PHP te doen, of één kleine library (afweging maken).
2. Echte bronnen valideren tegen live data (in deze ontwikkelomgeving was internet dicht): GOV.UK-slugs,
   AA-veldnamen, BuZa-teksten van alle landen door `buza_levels` halen en afwijkingen als test vastleggen.
3. Gelicentieerde nieuwsbron kiezen als de dienst commercieel wordt; nieuws per taal (DE/NL-media).
4. Regio's per land (bv. werklocatie in een regionaal waarschuwingsgebied).
5. Definitieve naam + merkonderzoek (BOIP/EUIPO), logo en kleuren; daarna `brand_name` en kleuren instellen.
6. Hosting: HTTPS verplicht (service worker, push), SMTP voor wp_mail, verwerkersovereenkomst met hoster.
