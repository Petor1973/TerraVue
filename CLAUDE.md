# Terravue (werknaam) — Travel Risk Monitor

Zelfstandige app van Peter Langerak (eigen onderneming). Gebruikers kiezen landen en zien per land het
officiële reisadvies en recent veiligheidsnieuws, en krijgen een push- en/of e-mailmelding als het
reisadvies van een gevolgd land wijzigt. Geleverd als **WordPress-plugin** op een eigen server, en installeerbaar als **PWA**.

Communicatie met Peter: **Nederlands**. UI: **Engels standaard**, met schakelaar EN / DE / NL.
Code en commentaar: Engels.

## Harde randvoorwaarden

- **Volledig los van SGL / Lely / elke werkgever.** Dit is een schone herbouw: geen code, teksten, data,
  namen, huisstijl of koppelingen (planning, SQL Server) van een werkgever gebruiken of overnemen.
  Waarschuw Peter direct als een wijziging daar toch richting gaat (werkgeversbeleid, IE, arbeidscontract).
- **AVG.** Persoonsgegevens beperkt tot: e-mailadres (WordPress-gebruiker), tijdstip van toestemming,
  gekozen landen, taal, gekozen adviesbron, en — alleen als de gebruiker ze aanzet — push-abonnementen per apparaat
  (endpoint + sleutels) en de keuze voor e-mailmeldingen. Nieuwe persoonsgegevens (bv. locatie, telefoonnummer) alleen
  na expliciete afweging, met privacytekst, exporter/eraser en bewaartermijn. **Geen locatietracking.**
  Geen externe fonts/CDN's/analytics in de frontend (geen IP-lekken naar derden).
- **Registratie** is double opt-in via magic link of 6-cijferige code (zelfde mail; code voor de
  geïnstalleerde iOS-app, die eigen cookies heeft). Max. 5 codepogingen. Accounts met `edit_posts`
  (beheerders, redacteuren) kunnen nooit via link of code inloggen.
- **Push** alleen naar bekende pushdiensten (`Notify::PUSH_HOSTS`: Google, Mozilla, Apple, Microsoft), nooit
  naar willekeurige URL's (SSRF). Payload altijd versleuteld (RFC 8291), VAPID-sleutels per site in een option.
- **Bronnen/licenties.** Reisadvies van zes overheden (zie tabel "Bronnen en licenties"); bronvermelding per
  bron in de app-footer (`Sources::ATTRIBUTION`), plus "niet verbonden aan een overheid". Nieuws: GDELT (standaard). Google News RSS is alleen voor persoonlijk,
  niet-commercieel gebruik — voor een betaalde dienst een gelicentieerde nieuwsbron kiezen.
- **Merk.** "Terravue" is een werknaam; nog geen merkonderzoek gedaan. "UNECTA" is een bestaand merk van
  een Braziliaans bedrijf: niet gebruiken als naam, en de stijl niet zo dicht benaderen dat verwarring ontstaat.
- Geen build-stap, geen Composer/npm-dependencies in de plugin. PHP 8.0+, WordPress 6.4+.

## UI-richtlijnen

- **Kopbalk** (merk, taalwissel, gebruikersmenu) staat altijd vast bovenaan (`position: sticky`).
- **Landen** als compacte tegels (kleur, naam, niveau, badges); klikken klapt de volledige info uit.
- **Installatie-instructies** alleen op telefoons/tablets (iOS incl. iPad-als-Mac via touch, Android),
  niet op laptops/MacBooks/Chromebooks en niet in de geïnstalleerde app. Kaart onderin boven de tabbalk;
  Android met `beforeinstallprompt` → één knop "Installeren". "Niet nu" = 14 dagen stil (localStorage);
  altijd terug te halen via het accountmenu en (iOS) vanuit Meldingen.
- **Geïnstalleerde app** opent `app-pagina?tr_app=1` met `templates/app.php`: geen thema-header/-footer.
  Rekening houden met `env(safe-area-inset-*)` (notch, home-indicator).
- **Navigatie:** **zwevende tabbalk ("eiland")** zoals in de App Store (nu: Landen, Meldingen): los van de onderrand, afgerond,
  deels transparant (`backdrop-filter: blur`), icoon + kort label per tab, actieve tab in accentkleur,
  ruimte voor `safe-area-inset-bottom`. Op brede schermen (≥ 900px, niet in de app) staat dezelfde
  navigatie in de kopbalk. Het gebruikersmenu rechtsboven blijft. Nieuwe schermen = nieuwe tab.

## Structuur

```
travel-risk/                 De plugin (deze map wordt gezipt en geüpload)
  travel-risk.php            Bootstrap, instellingen, helpers (countries(), cached(), http_get())
  includes/Sources.php       Reisadvies-adapters BuZa / FCDO / AA -> niveau 1..4 (zonder WP, testbaar)
  includes/News.php          Nieuws (GDELT, Google News RSS), ruisfilter (zonder WP, testbaar)
  includes/Rest.php          REST API travel-risk/v1
  includes/Auth.php          Magic link + 6-cijferige code / double opt-in, rate limiting
  includes/Notify.php        Meldingen: push-apparaten, uurlijkse controle (WP-Cron), push + e-mail
  includes/WebPush.php       VAPID (RFC 8292) + aes128gcm-versleuteling (RFC 8291), zonder library
  includes/Pwa.php           Manifest + service worker via /?travel_risk_pwa=manifest|sw
  includes/Frontend.php      Shortcode [travel_risk], assets, config naar JS
  includes/Privacy.php       AVG: exporter, eraser, voorgestelde privacytekst
  includes/Admin.php         Instellingen > Travel Risk
  templates/app.php          Kale pagina voor de geïnstalleerde app (?tr_app=1)
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
- FCDO: `details.alert_status` (whole_country / parts). Alleen regionale alerts -> level 2, max volgens alert.
  Alleen als de tekst "all but essential travel to the rest of ..." zegt (bv. Oekraïne) -> level 3. Twee
  "to_parts"-statussen alléén betekenen dat níet (Israël, sept. 2026: alleen delen, rest geen advies).
- AA: `warning` 4, `situationWarning` 3, `partialWarning` max 4, `situationPartWarning` max 3; vlaggen uit
  lijst én detail (OF). `situation*` was bedoeld voor COVID; het gewone "Von Reisen … wird abgeraten" staat
  alleen in de tekst → `aa_phrases()`: landelijk "abgeraten" = 3, "gewarnt" = 4; met gebiedswoord alleen max.
- BuZa: geen kleurveld; per zin geparsed (`Sources::buza_levels`). Zin met "grootste deel/rest van" wint;
  anders eerste zin zónder regio. Regio's ook in samenstellingen ("grensgebieden", "Gazastrook") en
  "tussen X en Y". **Bij twijfel: testgeval toevoegen** (Israël-teksten staan als voorbeeld in de tests).
- Elk advies heeft `basis`: de ruwe gegevens waarop het niveau berust (alert_status, AA-vlaggen + zin,
  beslissende BuZa-zin, US-titel, CA advisory-state, AU-omschrijving). De app toont dit als "Waarom dit niveau",
  zodat afwijkingen live te controleren zijn.
- **Na een plugin-update** (`maybe_upgrade()`, versie in option `travel_risk_version`): gecachet advies en de
  meldingen-nulmeting worden gewist, zodat parserverbeteringen geen valse "advies gewijzigd"-meldingen geven.
  Versie dus bij elke wijziging in de interpretatie ophogen.
- **Bron = keuze van de gebruiker** ("Advies van NL · UK · DE · US · CA · AU"), los van de UI-taal: het advies van de overheid
  van je nationaliteit/werkgever (daar hangt ook je reisverzekering aan). **Nooit op locatie** (AVG, geen
  tracking; en inhoudelijk fout: advies hangt af van wie je bent, niet waar je bent). Standaard uit de
  regio-instelling van de browser (`nl-NL` → NL, `de-*` → DE, `-US` → US, `-CA` → CA, `-AU` → AU, anders UK), client-side; opgeslagen in
  localStorage en user meta `travel_risk_source`; meldingen volgen dezelfde bron (`user_source()`).
  Uitgeklapte tegel toont de niveaus van de andere vijf overheden en de consensus ter vergelijking.
- Een bron geeft geen advies voor het eigen land. Samenvatting staat in de taal van de bron.

## Bronnen en licenties

| id | Overheid | Endpoint | Formaat / niveau | Licentie |
|---|---|---|---|---|
| buza | Nederland, BuZa | opendata.nederlandwereldwijd.nl v2, per land | XML; kleur uit tekst | open data — **licentie nog verifiëren** |
| fcdo | VK, FCDO | GOV.UK Content API, per land | `details.alert_status` | OGL v3, bronvermelding (geverifieerd) |
| aa | Duitsland, Auswärtiges Amt | opendata/travelwarning (+ detail) | booleans | open data — **licentie nog verifiëren** |
| usdos | VS, State Department | `travel.state.gov/_res/rss/TAsTWs.xml` (één feed) | "Level N" in titel; regionaal uit tekst | publiek domein, bronvermelding gewaardeerd |
| gac | Canada, Global Affairs | `data.international.gc.ca/travel-voyage/index-alpha-eng.json` | `advisory-state` 0–3 (+1); `has-regional-advisory` zonder niveau → `regional: true` | Open Government Licence – Canada |
| dfat | Australië, Smartraveller | `smartraveller.gov.au/countries/documents/index.rss` | `<ta:description>` (tekst → niveau); regionaal uit tekst | **licentie nog verifiëren** (vermoedelijk CC BY) |

- De JSON-API van de VS (`cadataapi.state.gov/api/TravelAdvisories`) is sinds sept. 2026 leeg; de RSS-feed werkt.
  travel.state.gov zit achter Cloudflare: nooit omzeilen (geen nep-user-agent). Bij 403: bron tijdelijk niet beschikbaar.
- Hele feeds (VS, CA, AU, AA-lijst) worden één keer opgehaald en gedeeld gecachet (`sources()` → `cached('feed:…')`).
- Landnamen in feeds wijken af ("Burma (Myanmar)", "Mainland China, Hong Kong & Macau", "Türkiye"):
  `Sources::name_matches()` + `alt`-aliassen in countries.json. Bij een nieuw land zonder treffer: alias toevoegen + test.
- **Consensus:** uitgeklapte tegel toont het meest genoemde landelijke niveau over alle overheden (gelijkspel → strenger)
  en het strengste niveau met de overheden die dat zeggen. Alleen bij ≥ 3 bronnen met een niveau.
- **Japan (MOFA) en Zuid-Korea (MOFA) bewust nog niet** (alleen zinvol voor de Aziatische markt):
  Japan: de open data (ezairyu.mofa.go.jp) bevat consulaire mails, géén actuele 危険レベル; niveaus staan alleen
  in HTML op anzen.mofa.go.jp (geen stabiliteitsgarantie). Licentie wel ruim (Japan Public Data License 1.0, commercieel toegestaan).
  Korea: data.go.kr `TravelAlarmService2` vereist een API-sleutel (aanvragen), antwoord in het Koreaans; responsvelden nog verifiëren.
  Frankrijk: geen actuele open API.

## API (travel-risk/v1)

`GET advice/{ISO3}?source=` (buza|fcdo|aa|usdos|gac|dfat; `?lang=` = standaardbron), `GET news/{ISO3}`, `GET|PUT|DELETE me` (PUT ook `notifyEmail`, `source`), `POST login`,
`POST login/verify` (`token` of `email`+`code`), `POST logout`, `GET push/key`, `POST|DELETE push`, `POST push/test`. Foutcodes als korte string (`rate_limited`, `not_found`, ...), vertaald in app.js.
Cache: transients, alleen succesvolle antwoorden, standaard 60 min. GDELT-aanroepen minimaal 6 s uit elkaar.

## Testen

```
php travel-risk/tests/unit.php
wp eval-file wp-content/plugins/travel-risk/tests/wp-smoke.php   # in een test-WordPress
```

## Meldingen (hoe het werkt)

Elk uur (`travel_risk_check`, WP-Cron) haalt `Notify::check()` voor elk paar (bron, land) dat gebruikers
met meldingen volgen het advies op (bron = taal van de gebruiker), vergelijkt `level`/`maxLevel` met de
vorige run (option `travel_risk_snapshots`) en stuurt bij verschil push en/of e-mail in de taal van de
gebruiker. Eerste run per paar = alleen nulmeting. Bij een storing blijft de oude nulmeting staan.
Pushdienst antwoordt 404/410 → apparaat wordt verwijderd. Uitloggen/account wissen verwijdert het apparaat.
iOS: push alleen in de app op het beginscherm (iOS 16.4+). Echte cron aanbevolen (zie instellingenpagina).

## Backlog (voorstel)

1. Meldingen verfijnen: ook bij inhoudelijke wijziging zonder niveauwijziging (bv. `updated`), stille
   uren, samenvatting per dag. Push getest met onafhankelijke decryptie (http_ece), nog niet tegen een
   echte pushdienst: eerste keer live testen met de knop "Testmelding sturen".
2. Echte bronnen valideren tegen live data (in deze ontwikkelomgeving was internet dicht): GOV.UK-slugs,
   AA-veldnamen, BuZa-teksten van alle landen door `buza_levels` halen en afwijkingen als test vastleggen;
   US/AU-landnamen tegen `name_matches()` (ontbrekende treffers → alias); licenties buza/aa/dfat bevestigen.
3. Gelicentieerde nieuwsbron kiezen als de dienst commercieel wordt; nieuws per taal (DE/NL-media).
4. Regio's per land (bv. werklocatie in een regionaal waarschuwingsgebied).
5. Definitieve naam + merkonderzoek (BOIP/EUIPO), logo en kleuren; daarna `brand_name` en kleuren instellen.
6. Hosting: HTTPS verplicht (service worker, push), SMTP voor wp_mail, verwerkersovereenkomst met hoster.
