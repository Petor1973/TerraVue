/* Travel Risk app. Vanilla JS, no build step. Config in window.TRAVEL_RISK (see Frontend.php). */
(() => {
  'use strict';

  const cfg = window.TRAVEL_RISK;
  const root = document.getElementById('travel-risk-app');
  if (!cfg || !root) return;

  // ---------------------------------------------------------------- texts
  const T = {
    en: {
      tagline: 'Official travel advice and disaster alerts for the countries where your people work.',
      language: 'Language', search: 'Add a country, e.g. Saudi Arabia', searchLabel: 'Search country',
      refresh: 'Refresh', remove: 'Remove {name}', install: 'Install app',
      empty: 'No countries yet. Type a country above to see its travel advice and current alerts.',
      sumNone: 'Add the countries where your people work.',
      sumLoading: '{n} countries selected, loading…',
      sumAttention: '{a} of {n} countries have an elevated risk level.',
      sumOk: 'All {n} countries: normal precautions.',
      level: ['Unknown', 'Normal precautions', 'Exercise caution', 'Essential travel only', 'Do not travel'],
      partial: 'Parts of the country: {level}. Check whether the work location is in that area.',
      noLevel: 'Risk level not recognised. Please read the advice yourself.',
      updated: 'Updated {date}', source: 'Source: {source}', summary: 'Summary of the advice',
      fullAdvice: 'Full travel advice', newsTitle: 'News, last {h} hours', newsLoading: 'Looking for news…',
      newsNone: 'No reports about security incidents found.', loading: 'Loading travel advice…',
      newsCount: '{n} news', parts: 'Parts: {level}', expandAll: 'Expand all', collapseAll: 'Collapse all',
      menu: 'Menu', tabCountries: 'Countries', tabAlerts: 'Notifications', tabSettings: 'Settings',
      settingsTitle: 'Settings', sourcesTitle: 'Travel advice and sources', aboutTitle: 'About {brand}', version: 'Version {v}',
      tourMenu: 'Show the tour', tourNext: 'Next', tourBack: 'Back', tourDone: 'Done', tourSkip: 'Skip', tourStep: 'Step {i} of {n}',
      tour: {
        welcome: ['Welcome to {brand}', 'A short tour of the app in a few steps.'],
        search: ['Add your countries', 'Type a country here, for example where your people work. Your list is saved with your account.'],
        tile: ['One tile per country', 'The colour shows the advice level; labels flag regional warnings, disaster alerts and recent updates. Tap a tile for the full advice, the other governments and current alerts.'],
        alerts: ['Notifications', 'Turn on push or e-mail here to hear when the advice for one of your countries changes or a disaster alert is issued.'],
        settings: ['Settings', 'Choose whose government advice you follow, see the sources and licences, and find the app version.'],
        account: ['Your account', 'Install the app, show this tour again, or sign out.'],
      },
      adviceFrom: 'Advice from',
      adviceHint: 'Choose the government whose advice you follow, usually that of your nationality or your employer. It applies to all countries and to notifications.',
      otherSources: 'Other governments', inLanguage: 'Summary in {lang}.', followSource: 'Follow the advice of {source} (for all countries)', basis: 'Why this level',
      sourceLang: { buza: 'Dutch', fcdo: 'English', aa: 'German', usdos: 'English', gac: 'English' },
      consensus: '{n} governments: most say “{most}”; strictest “{max}” ({who}).', regionalUnknown: 'Stricter advice applies to parts of the country. Check the regional details in the full advice.', regionalBadge: 'Regional warnings',
      latest: 'Latest update', recentBadge: 'Recently updated',
      gdacsTitle: 'Disaster alerts (GDACS)', gdacsNone: 'No current disaster alerts.', gdacsLang: 'Alert texts in English.',
      hazard: { EQ: 'Earthquake', TC: 'Tropical cyclone', FL: 'Flood', VO: 'Volcano', DR: 'Drought', WF: 'Wildfire', TS: 'Tsunami' },
      alertLevel: { green: 'Green alert', orange: 'Orange alert', red: 'Red alert' },
      allSources: 'Sources and licences', notEndorsed: 'Not affiliated with or endorsed by any government.',
      installMenu: 'Install as app', installTitle: 'Install {brand} as an app',
      installLead: 'Put {brand} on your home screen: it opens full screen like an app, works offline and can send you notifications.',
      iosSteps: ['Tap the Share button {share} in the toolbar (bottom on iPhone, top on iPad).', 'Scroll down and tap “Add to Home Screen” {add}.', 'Tap “Add”, then open {brand} from your home screen.'],
      iosNote: 'No “Add to Home Screen”? Open this page in Safari.',
      androidSteps: ['Open the browser menu {menu} at the top right.', 'Tap “Install app” or “Add to Home screen”.', 'Confirm, then open {brand} from your home screen.'],
      androidOneTap: 'Tap “Install” below; {brand} will then appear on your home screen.',
      installNow: 'Install', notNow: 'Not now', howTo: 'Show me how',
      codeLabel: 'Using the installed app? Enter the 6-digit code from the e-mail', codeButton: 'Sign in',
      alertsTitle: 'Notifications',
      alertsLead: 'Get a message when the travel advice for one of your countries changes. The official advice is checked every hour.', alertsLeadGdacs: 'You also get a message when GDACS issues an orange or red disaster alert (earthquake, cyclone, flood, …) for one of your countries.',
      pushTitle: 'Push notifications on this device', pushOn: 'On for this device.', pushOff: 'Off for this device.',
      pushAccount: 'Push is on for {n} device(s) of {email}. Devices signed in with another account get their own notifications.',
      pushDenied: 'Notifications are blocked for this site. Allow them in your browser or phone settings and try again.',
      pushUnsupported: 'This browser does not support push notifications. You can use e-mail notifications instead.',
      pushIos: 'iPhone and iPad: first add this app to your home screen (Share → Add to Home Screen), open it from there and turn notifications on in the app.',
      pushConsent: 'Turning this on stores the push address of this device with your account. Turn it off at any time.',
      pushFailed: 'Could not turn on notifications on this device. Please try again later.',
      emailTitle: 'E-mail notifications', emailText: 'Also send an e-mail to {email} when the advice changes.',
      follows: 'Notifications cover the {n} countries you follow, with advice from {source}.',
      followsNone: 'You do not follow any countries yet. Add them under Countries.',
      updatedAt: 'Updated at {time}.', offline: 'You are offline. Showing the last saved information.',
      account: 'Account', signOut: 'Sign out', deleteAccount: 'Delete account',
      deleteConfirm: 'Delete your account and all saved data? This cannot be undone.',
      regTitle: 'Welcome to {brand}',
      regText: 'Register once with your e-mail address. You will receive a sign-in link; no password needed.',
      email: 'E-mail address',
      consent: 'I agree that my e-mail address, the countries I add and my language are stored to provide this service.',
      privacy: 'Privacy policy', sendLink: 'Send sign-in link', sending: 'Sending…',
      sentTitle: 'Check your inbox',
      sentText: 'If the address is valid, a sign-in link is on its way to {email}. It is valid for 30 minutes.',
      otherEmail: 'Use another address', signingIn: 'Signing in…',
      footer: 'Travel advice: {source}. Advice is normalised to four levels; always check the official advice for regional details.',
      footerNews: 'News: {news}, found automatically with security keywords in English-language media; not every item is relevant.',
      err: {
        not_found: 'No travel advice available from this source.',
        no_home_advice: 'This source does not publish advice for its own country.',
        rate_limited: 'The news service is busy. Try again in a minute.',
        unexpected_response: 'Unexpected answer from the source.',
        consent_required: 'Please give your consent to continue.',
        invalid_email: 'Please enter a valid e-mail address.',
        expired_token: 'This sign-in link has expired or was already used. Request a new one.',
        invalid_token: 'This sign-in link is not valid. Request a new one.',
        rest_forbidden: 'Please sign in first.',
        invalid_code: 'This code is not correct.', invalid_subscription: 'This device could not be registered.',
        offline: 'No connection.',
        default: 'Source not reachable. Try again later.',
      },
    },
    de: {
      tagline: 'Offizielle Reisehinweise und Katastrophenwarnungen für die Länder, in denen Ihre Leute arbeiten.',
      language: 'Sprache', search: 'Land hinzufügen, z. B. Saudi-Arabien', searchLabel: 'Land suchen',
      refresh: 'Aktualisieren', remove: '{name} entfernen', install: 'App installieren',
      empty: 'Noch keine Länder. Geben Sie oben ein Land ein, um Reisehinweise und aktuelle Warnungen zu sehen.',
      sumNone: 'Fügen Sie die Länder hinzu, in denen Ihre Leute arbeiten.',
      sumLoading: '{n} Länder ausgewählt, wird geladen…',
      sumAttention: '{a} von {n} Ländern haben ein erhöhtes Risiko.',
      sumOk: 'Alle {n} Länder: normale Vorsicht.',
      level: ['Unbekannt', 'Normale Vorsicht', 'Erhöhte Vorsicht', 'Nur notwendige Reisen', 'Reisewarnung'],
      partial: 'Teile des Landes: {level}. Prüfen Sie, ob der Einsatzort in diesem Gebiet liegt.',
      noLevel: 'Risikostufe nicht erkannt. Bitte lesen Sie den Hinweis selbst.',
      updated: 'Aktualisiert am {date}', source: 'Quelle: {source}', summary: 'Zusammenfassung',
      fullAdvice: 'Vollständige Reise- und Sicherheitshinweise', newsTitle: 'Nachrichten, letzte {h} Stunden',
      newsLoading: 'Suche nach Nachrichten…', newsNone: 'Keine Meldungen über Sicherheitsvorfälle gefunden.',
      newsCount: '{n} Meldungen', parts: 'Teilweise: {level}', expandAll: 'Alle aufklappen', collapseAll: 'Alle zuklappen',
      menu: 'Menü', tabCountries: 'Länder', tabAlerts: 'Benachrichtigungen', tabSettings: 'Einstellungen',
      settingsTitle: 'Einstellungen', sourcesTitle: 'Reisehinweise und Quellen', aboutTitle: 'Über {brand}', version: 'Version {v}',
      tourMenu: 'Einführung zeigen', tourNext: 'Weiter', tourBack: 'Zurück', tourDone: 'Fertig', tourSkip: 'Überspringen', tourStep: 'Schritt {i} von {n}',
      tour: {
        welcome: ['Willkommen bei {brand}', 'Eine kurze Einführung in die App in wenigen Schritten.'],
        search: ['Länder hinzufügen', 'Geben Sie hier ein Land ein, z. B. wo Ihre Leute arbeiten. Ihre Liste wird mit Ihrem Konto gespeichert.'],
        tile: ['Eine Kachel pro Land', 'Die Farbe zeigt die Hinweisstufe; Kennzeichen markieren regionale Warnungen, Katastrophenwarnungen und neue Änderungen. Tippen Sie auf eine Kachel für den vollständigen Hinweis, die anderen Regierungen und aktuelle Warnungen.'],
        alerts: ['Benachrichtigungen', 'Schalten Sie hier Push oder E-Mail ein, um zu erfahren, wenn sich der Hinweis für eines Ihrer Länder ändert oder eine Katastrophenwarnung erscheint.'],
        settings: ['Einstellungen', 'Wählen Sie, welcher Regierung Sie folgen, und finden Sie Quellen, Lizenzen und die App-Version.'],
        account: ['Ihr Konto', 'App installieren, diese Einführung erneut zeigen oder abmelden.'],
      },
      adviceFrom: 'Hinweise von',
      adviceHint: 'Wählen Sie die Regierung, deren Hinweisen Sie folgen, meist die Ihrer Staatsangehörigkeit oder Ihres Arbeitgebers. Gilt für alle Länder und Benachrichtigungen.',
      otherSources: 'Andere Regierungen', inLanguage: 'Zusammenfassung auf {lang}.', followSource: 'Hinweisen von {source} folgen (für alle Länder)', basis: 'Grundlage',
      sourceLang: { buza: 'Niederländisch', fcdo: 'Englisch', aa: 'Deutsch', usdos: 'Englisch', gac: 'Englisch' },
      consensus: '{n} Regierungen: die meisten sagen „{most}“; am strengsten „{max}“ ({who}).', regionalUnknown: 'Für Teile des Landes gelten strengere Hinweise. Prüfen Sie die regionalen Angaben im vollständigen Hinweis.', regionalBadge: 'Regionale Warnungen',
      latest: 'Letzte Änderung', recentBadge: 'Kürzlich geändert',
      gdacsTitle: 'Katastrophenwarnungen (GDACS)', gdacsNone: 'Keine aktuellen Katastrophenwarnungen.', gdacsLang: 'Warntexte auf Englisch.',
      hazard: { EQ: 'Erdbeben', TC: 'Tropischer Wirbelsturm', FL: 'Überschwemmung', VO: 'Vulkan', DR: 'Dürre', WF: 'Waldbrand', TS: 'Tsunami' },
      alertLevel: { green: 'Grüne Warnung', orange: 'Orange Warnung', red: 'Rote Warnung' },
      allSources: 'Quellen und Lizenzen', notEndorsed: 'Nicht mit einer Regierung verbunden oder von ihr unterstützt.',
      installMenu: 'Als App installieren', installTitle: '{brand} als App installieren',
      installLead: 'Legen Sie {brand} auf Ihren Home-Bildschirm: Die App öffnet im Vollbild, funktioniert offline und kann Ihnen Benachrichtigungen senden.',
      iosSteps: ['Tippen Sie auf die Teilen-Taste {share} in der Symbolleiste (unten auf dem iPhone, oben auf dem iPad).', 'Scrollen Sie nach unten und tippen Sie auf „Zum Home-Bildschirm“ {add}.', 'Tippen Sie auf „Hinzufügen“ und öffnen Sie {brand} dann vom Home-Bildschirm.'],
      iosNote: 'Kein „Zum Home-Bildschirm“? Öffnen Sie diese Seite in Safari.',
      androidSteps: ['Öffnen Sie oben rechts das Browsermenü {menu}.', 'Tippen Sie auf „App installieren“ oder „Zum Startbildschirm hinzufügen“.', 'Bestätigen Sie und öffnen Sie {brand} dann vom Startbildschirm.'],
      androidOneTap: 'Tippen Sie unten auf „Installieren“; {brand} erscheint dann auf Ihrem Startbildschirm.',
      installNow: 'Installieren', notNow: 'Später', howTo: 'Anleitung zeigen',
      codeLabel: 'Sie nutzen die installierte App? Geben Sie den 6-stelligen Code aus der E-Mail ein', codeButton: 'Anmelden',
      alertsTitle: 'Benachrichtigungen',
      alertsLead: 'Erhalten Sie eine Nachricht, wenn sich der Reisehinweis für eines Ihrer Länder ändert. Die offiziellen Hinweise werden stündlich geprüft.', alertsLeadGdacs: 'Sie erhalten auch eine Nachricht, wenn GDACS für eines Ihrer Länder eine orange oder rote Katastrophenwarnung (Erdbeben, Wirbelsturm, Überschwemmung, …) herausgibt.',
      pushTitle: 'Push-Benachrichtigungen auf diesem Gerät', pushOn: 'Auf diesem Gerät aktiv.', pushOff: 'Auf diesem Gerät aus.',
      pushAccount: 'Push ist für {n} Gerät(e) von {email} aktiv. Geräte mit einem anderen Konto erhalten eigene Benachrichtigungen.',
      pushDenied: 'Benachrichtigungen sind für diese Website blockiert. Erlauben Sie sie in den Browser- oder Telefoneinstellungen und versuchen Sie es erneut.',
      pushUnsupported: 'Dieser Browser unterstützt keine Push-Benachrichtigungen. Nutzen Sie stattdessen E-Mail-Benachrichtigungen.',
      pushIos: 'iPhone und iPad: Fügen Sie die App zuerst zum Home-Bildschirm hinzu (Teilen → Zum Home-Bildschirm), öffnen Sie sie dort und aktivieren Sie die Benachrichtigungen in der App.',
      pushConsent: 'Beim Aktivieren wird die Push-Adresse dieses Geräts in Ihrem Konto gespeichert. Sie können das jederzeit abschalten.',
      pushFailed: 'Benachrichtigungen konnten auf diesem Gerät nicht aktiviert werden. Bitte später erneut versuchen.',
      emailTitle: 'E-Mail-Benachrichtigungen', emailText: 'Zusätzlich eine E-Mail an {email} senden, wenn sich der Hinweis ändert.',
      follows: 'Benachrichtigungen gelten für die {n} Länder, denen Sie folgen, mit Hinweisen von {source}.',
      followsNone: 'Sie folgen noch keinen Ländern. Fügen Sie sie unter Länder hinzu.',
      loading: 'Reisehinweise werden geladen…', updatedAt: 'Aktualisiert um {time}.',
      offline: 'Sie sind offline. Es werden die zuletzt gespeicherten Daten angezeigt.',
      account: 'Konto', signOut: 'Abmelden', deleteAccount: 'Konto löschen',
      deleteConfirm: 'Konto und alle gespeicherten Daten löschen? Dies kann nicht rückgängig gemacht werden.',
      regTitle: 'Willkommen bei {brand}',
      regText: 'Registrieren Sie sich einmalig mit Ihrer E-Mail-Adresse. Sie erhalten einen Anmeldelink; ein Passwort ist nicht nötig.',
      email: 'E-Mail-Adresse',
      consent: 'Ich willige ein, dass meine E-Mail-Adresse, die hinzugefügten Länder und meine Sprache zur Bereitstellung dieses Dienstes gespeichert werden.',
      privacy: 'Datenschutzerklärung', sendLink: 'Anmeldelink senden', sending: 'Wird gesendet…',
      sentTitle: 'Prüfen Sie Ihr Postfach',
      sentText: 'Wenn die Adresse gültig ist, ist ein Anmeldelink an {email} unterwegs. Er ist 30 Minuten gültig.',
      otherEmail: 'Andere Adresse verwenden', signingIn: 'Anmeldung läuft…',
      footer: 'Reisehinweise: {source}. Hinweise sind auf vier Stufen vereinheitlicht; prüfen Sie für regionale Details immer den offiziellen Hinweis.',
      footerNews: 'Nachrichten: {news}, automatisch über Sicherheitsbegriffe in englischsprachigen Medien gefunden; nicht jede Meldung ist relevant.',
      err: {
        not_found: 'Diese Quelle bietet für dieses Land keine Hinweise.',
        no_home_advice: 'Diese Quelle veröffentlicht keine Hinweise für das eigene Land.',
        rate_limited: 'Der Nachrichtendienst ist ausgelastet. Bitte in einer Minute erneut versuchen.',
        unexpected_response: 'Unerwartete Antwort der Quelle.',
        consent_required: 'Bitte erteilen Sie Ihre Einwilligung, um fortzufahren.',
        invalid_email: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
        expired_token: 'Dieser Anmeldelink ist abgelaufen oder wurde bereits verwendet. Fordern Sie einen neuen an.',
        invalid_token: 'Dieser Anmeldelink ist ungültig. Fordern Sie einen neuen an.',
        rest_forbidden: 'Bitte melden Sie sich zuerst an.',
        invalid_code: 'Dieser Code ist nicht korrekt.', invalid_subscription: 'Dieses Gerät konnte nicht registriert werden.',
        offline: 'Keine Verbindung.',
        default: 'Quelle nicht erreichbar. Bitte später erneut versuchen.',
      },
    },
    nl: {
      tagline: 'Officieel reisadvies en rampenmeldingen voor de landen waar jouw mensen werken.',
      language: 'Taal', search: 'Land toevoegen, bv. Saoedi-Arabië', searchLabel: 'Land zoeken',
      refresh: 'Vernieuwen', remove: '{name} verwijderen', install: 'App installeren',
      empty: 'Nog geen landen. Typ hierboven een land om het reisadvies en actuele meldingen te zien.',
      sumNone: 'Voeg de landen toe waar jouw mensen werken.',
      sumLoading: '{n} landen gekozen, gegevens worden opgehaald…',
      sumAttention: '{a} van de {n} landen hebben een verhoogd risico.',
      sumOk: 'Alle {n} landen: normale voorzorg.',
      level: ['Onbekend', 'Normale voorzorg', 'Let op', 'Alleen noodzakelijke reizen', 'Niet reizen'],
      partial: 'Delen van het land: {level}. Controleer of de werklocatie in dat gebied ligt.',
      noLevel: 'Risiconiveau niet herkend. Lees het advies zelf na.',
      updated: 'Gewijzigd op {date}', source: 'Bron: {source}', summary: 'Samenvatting van het advies',
      fullAdvice: 'Volledig reisadvies', newsTitle: 'Nieuws, afgelopen {h} uur', newsLoading: 'Nieuws zoeken…',
      newsNone: 'Geen berichten over veiligheidsincidenten gevonden.', loading: 'Reisadvies ophalen…',
      newsCount: '{n} berichten', parts: 'Deels: {level}', expandAll: 'Alles uitklappen', collapseAll: 'Alles inklappen',
      menu: 'Menu', tabCountries: 'Landen', tabAlerts: 'Meldingen', tabSettings: 'Instellingen',
      settingsTitle: 'Instellingen', sourcesTitle: 'Reisadvies en bronnen', aboutTitle: 'Over {brand}', version: 'Versie {v}',
      tourMenu: 'Rondleiding tonen', tourNext: 'Volgende', tourBack: 'Vorige', tourDone: 'Klaar', tourSkip: 'Overslaan', tourStep: 'Stap {i} van {n}',
      tour: {
        welcome: ['Welkom bij {brand}', 'Een korte rondleiding door de app in een paar stappen.'],
        search: ['Landen toevoegen', 'Typ hier een land, bijvoorbeeld waar jouw mensen werken. Je lijst wordt bij je account bewaard.'],
        tile: ['Eén tegel per land', 'De kleur toont het adviesniveau; labels wijzen op regionale waarschuwingen, rampenmeldingen en recente wijzigingen. Tik op een tegel voor het volledige advies, de andere overheden en actuele meldingen.'],
        alerts: ['Meldingen', 'Zet hier push of e-mail aan om te horen wanneer het advies voor een van je landen wijzigt of er een rampenmelding is.'],
        settings: ['Instellingen', 'Kies welke overheid je volgt, en vind hier de bronnen, licenties en het versienummer van de app.'],
        account: ['Je account', 'App installeren, deze rondleiding opnieuw bekijken of uitloggen.'],
      },
      adviceFrom: 'Advies van',
      adviceHint: 'Kies de overheid waarvan je het advies volgt, meestal die van je nationaliteit of werkgever. Geldt voor alle landen en voor meldingen.',
      otherSources: 'Andere overheden', inLanguage: 'Samenvatting in het {lang}.', followSource: 'Advies van {source} volgen (voor alle landen)', basis: 'Waarom dit niveau',
      sourceLang: { buza: 'Nederlands', fcdo: 'Engels', aa: 'Duits', usdos: 'Engels', gac: 'Engels' },
      consensus: '{n} overheden: de meeste zeggen ‘{most}’; strengst ‘{max}’ ({who}).', regionalUnknown: 'Voor delen van het land geldt een strenger advies. Bekijk de regionale details in het volledige advies.', regionalBadge: 'Regionale waarschuwingen',
      latest: 'Laatste wijziging', recentBadge: 'Recent gewijzigd',
      gdacsTitle: 'Rampenmeldingen (GDACS)', gdacsNone: 'Geen actuele rampenmeldingen.', gdacsLang: 'Meldingsteksten in het Engels.',
      hazard: { EQ: 'Aardbeving', TC: 'Tropische cycloon', FL: 'Overstroming', VO: 'Vulkaan', DR: 'Droogte', WF: 'Bosbrand', TS: 'Tsunami' },
      alertLevel: { green: 'Groene melding', orange: 'Oranje melding', red: 'Rode melding' },
      allSources: 'Bronnen en licenties', notEndorsed: 'Niet verbonden aan of goedgekeurd door een overheid.',
      installMenu: 'Installeren als app', installTitle: '{brand} als app installeren',
      installLead: 'Zet {brand} op je beginscherm: de app opent schermvullend, werkt offline en kan je meldingen sturen.',
      iosSteps: ['Tik op de deelknop {share} in de werkbalk (onderaan op iPhone, bovenaan op iPad).', 'Scroll omlaag en tik op ‘Zet op beginscherm’ {add}.', 'Tik op ‘Voeg toe’ en open {brand} daarna vanaf je beginscherm.'],
      iosNote: 'Zie je ‘Zet op beginscherm’ niet? Open deze pagina in Safari.',
      androidSteps: ['Open rechtsboven het browsermenu {menu}.', 'Tik op ‘App installeren’ of ‘Toevoegen aan startscherm’.', 'Bevestig en open {brand} daarna vanaf je startscherm.'],
      androidOneTap: 'Tik hieronder op ‘Installeren’; {brand} verschijnt dan op je startscherm.',
      installNow: 'Installeren', notNow: 'Niet nu', howTo: 'Laat zien hoe',
      codeLabel: 'Gebruik je de geïnstalleerde app? Vul de 6-cijferige code uit de e-mail in', codeButton: 'Inloggen',
      alertsTitle: 'Meldingen',
      alertsLead: 'Krijg een bericht als het reisadvies voor een van je landen wijzigt. Het officiële advies wordt elk uur gecontroleerd.', alertsLeadGdacs: 'Je krijgt ook een bericht als GDACS een oranje of rode rampenmelding (aardbeving, cycloon, overstroming, …) geeft voor een van je landen.',
      pushTitle: 'Pushmeldingen op dit apparaat', pushOn: 'Aan op dit apparaat.', pushOff: 'Uit op dit apparaat.',
      pushAccount: 'Push staat aan voor {n} apparaat/apparaten van {email}. Apparaten met een ander account krijgen hun eigen meldingen.',
      pushDenied: 'Meldingen zijn geblokkeerd voor deze site. Sta ze toe in de instellingen van je browser of telefoon en probeer het opnieuw.',
      pushUnsupported: 'Deze browser ondersteunt geen pushmeldingen. Gebruik in plaats daarvan e-mailmeldingen.',
      pushIos: 'iPhone en iPad: zet de app eerst op je beginscherm (Deel → Zet op beginscherm), open hem daarvandaan en zet de meldingen in de app aan.',
      pushConsent: 'Als je dit aanzet, bewaren we het pushadres van dit apparaat bij je account. Je kunt het altijd weer uitzetten.',
      pushFailed: 'Meldingen konden niet worden aangezet op dit apparaat. Probeer het later opnieuw.',
      emailTitle: 'E-mailmeldingen', emailText: 'Stuur ook een e-mail naar {email} als het advies wijzigt.',
      follows: 'Meldingen gelden voor de {n} landen die je volgt, met advies van {source}.',
      followsNone: 'Je volgt nog geen landen. Voeg ze toe onder Landen.',
      updatedAt: 'Bijgewerkt om {time}.', offline: 'Je bent offline. De laatst bewaarde gegevens worden getoond.',
      account: 'Account', signOut: 'Uitloggen', deleteAccount: 'Account verwijderen',
      deleteConfirm: 'Je account en alle bewaarde gegevens verwijderen? Dit kan niet ongedaan worden gemaakt.',
      regTitle: 'Welkom bij {brand}',
      regText: 'Registreer je eenmalig met je e-mailadres. Je krijgt een inloglink; een wachtwoord is niet nodig.',
      email: 'E-mailadres',
      consent: 'Ik ga ermee akkoord dat mijn e-mailadres, de landen die ik toevoeg en mijn taal worden bewaard om deze dienst te leveren.',
      privacy: 'Privacyverklaring', sendLink: 'Inloglink versturen', sending: 'Versturen…',
      sentTitle: 'Kijk in je inbox',
      sentText: 'Als het adres geldig is, is er een inloglink onderweg naar {email}. De link is 30 minuten geldig.',
      otherEmail: 'Ander adres gebruiken', signingIn: 'Inloggen…',
      footer: 'Reisadvies: {source}. Adviezen zijn omgezet naar vier niveaus; controleer voor regionale details altijd het officiële advies.',
      footerNews: 'Nieuws: {news}, automatisch gezocht op veiligheidstrefwoorden in Engelstalige media; niet elk bericht is relevant.',
      err: {
        not_found: 'Deze bron heeft geen reisadvies voor dit land.',
        no_home_advice: 'Deze bron publiceert geen advies voor het eigen land.',
        rate_limited: 'De nieuwsdienst is druk. Probeer het over een minuut opnieuw.',
        unexpected_response: 'Onverwacht antwoord van de bron.',
        consent_required: 'Geef toestemming om verder te gaan.',
        invalid_email: 'Vul een geldig e-mailadres in.',
        expired_token: 'Deze inloglink is verlopen of al gebruikt. Vraag een nieuwe aan.',
        invalid_token: 'Deze inloglink is niet geldig. Vraag een nieuwe aan.',
        rest_forbidden: 'Log eerst in.',
        invalid_code: 'Deze code klopt niet.', invalid_subscription: 'Dit apparaat kon niet worden aangemeld.',
        offline: 'Geen verbinding.',
        default: 'Bron niet bereikbaar. Probeer het later opnieuw.',
      },
    },
  };
  const LOCALE = { en: 'en-GB', de: 'de-DE', nl: 'nl-NL' };
  const SOURCE = cfg.sources;             // id -> full name, from Sources::NAMES
  const SOURCES = Object.keys(SOURCE);
  const SOURCE_SHORT = { buza: 'NL', fcdo: 'UK', aa: 'DE', usdos: 'US', gac: 'CA' };

  // Default advice source from the browser's language/region settings; no location is used.
  function defaultSource() {
    for (const tag of navigator.languages || [navigator.language || '']) {
      const [lang, region] = tag.split('-').map(x => (x || '').toUpperCase());
      if (region === 'NL' || (lang === 'NL' && !region)) return 'buza';
      if (['DE', 'AT', 'CH', 'LI', 'LU'].includes(region) && lang === 'DE') return 'aa';
      if (lang === 'DE' && !region) return 'aa';
      if (region === 'GB' || region === 'UK') return 'fcdo';
      if (region === 'US') return 'usdos';
      if (region === 'CA') return 'gac';
    }
    return 'fcdo';
  }
  const NEWS = { gdelt: 'GDELT', google: 'Google News' };
  const RECENT_DAYS = 3; // "Recently updated" badge

  // ---------------------------------------------------------------- state
  const KEY = { lang: 'travel-risk.lang', countries: 'travel-risk.countries', guide: 'travel-risk.install-guide', source: 'travel-risk.source', tour: 'travel-risk.tour' };
  const store = {
    get(k, fallback) { try { const v = localStorage.getItem(k); return v === null ? fallback : JSON.parse(v); } catch { return fallback; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* private mode */ } },
    del(k) { try { localStorage.removeItem(k); } catch { /* ignore */ } },
  };

  const state = {
    lang: cfg.languages.includes(store.get(KEY.lang)) ? store.get(KEY.lang) : cfg.languages[0],
    source: null,           // buza | fcdo | aa, set in start()
    others: {},             // iso -> { source -> advice | {error} } for the comparison
    countries: [],          // [{iso3, en, de, nl, ...}]
    selected: store.get(KEY.countries, []),
    advice: {},             // iso -> {loading} | data | {error}
    news: {},               // iso -> {loading} | data | {error}
    gdacs: null,            // null | {loading} | {items: [...]} | {error}: all current GDACS alerts
    me: { loggedIn: false, registrationRequired: false },
    view: 'loading',        // loading | auth | sent | app
    open: new Set(),        // countries shown with full details
    tab: 'countries',       // countries | alerts | settings
    push: 'unknown',        // unknown | unsupported | denied | off | on | busy
    notice: '', code: '',
    email: '', consent: false, sentTo: '', authError: '', menuOpen: false, installPrompt: null, updatedAt: null,
    guide: false,           // install instructions sheet open
    tour: null,             // index of the current tour step, or null
  };

  const t = (key, vars = {}) => {
    const s = T[state.lang][key] ?? T.en[key] ?? key;
    return typeof s === 'string' ? s.replace(/\{(\w+)\}/g, (_, k) => vars[k] ?? '') : s;
  };
  const errText = code => T[state.lang].err[code] || T[state.lang].err[/^http_|unreachable/.test(code) ? 'default' : code] || T[state.lang].err.default;
  const byIso = iso => state.countries.find(c => c.iso3 === iso);
  const cname = iso => (byIso(iso) || {})[state.lang] || iso;

  // ---------------------------------------------------------------- DOM helper
  function h(tag, props = {}, ...children) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(props)) {
      if (v == null || v === false) continue;
      if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (k === 'class') el.className = v;
      else if (k in el && k !== 'list' && k !== 'form') el[k] = v;
      else el.setAttribute(k, v === true ? '' : v);
    }
    children.flat(Infinity).forEach(c => c != null && c !== false && el.append(c.nodeType ? c : String(c)));
    return el;
  }

  // ---------------------------------------------------------------- API
  function apiUrl(path, query = {}) {
    const base = cfg.rest + path;
    const qs = new URLSearchParams(query).toString();
    return qs ? base + (base.includes('?') ? '&' : '?') + qs : base;
  }

  async function api(method, path, body, query) {
    let res;
    try {
      res = await fetch(apiUrl(path, query), {
        method,
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': cfg.nonce, ...(body ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? JSON.stringify(body) : undefined,
      });
    } catch {
      throw new Error('offline');
    }
    const data = await res.json().catch(() => ({}));
    if (res.status === 403 && data.code === 'rest_cookie_invalid_nonce') {
      // Session nonce expired (page open for a long time): reload once for a fresh one.
      if (!sessionStorage.getItem('travel-risk.reloaded')) {
        sessionStorage.setItem('travel-risk.reloaded', '1');
        location.reload();
      }
    }
    if (!res.ok) throw new Error(data.code || 'http_' + res.status);
    sessionStorage.removeItem('travel-risk.reloaded');
    return data;
  }

  // ---------------------------------------------------------------- data
  function saveCountries() {
    store.set(KEY.countries, state.selected);
    if (state.me.loggedIn) api('PUT', 'me', { countries: state.selected }).catch(() => {});
  }

  async function loadAdvice(iso) {
    state.advice[iso] = { loading: true };
    render();
    try {
      state.advice[iso] = await api('GET', 'advice/' + iso, null, { source: state.source });
    } catch (e) {
      state.advice[iso] = { error: e.message };
    }
    state.updatedAt = new Date();
    render();
  }

  // News is fetched one country at a time; the server spaces calls to GDELT.
  let newsQueue = Promise.resolve();
  function loadNews(iso) {
    if (!cfg.news) return;
    state.news[iso] = { loading: true };
    newsQueue = newsQueue.then(async () => {
      if (!state.selected.includes(iso)) return;
      try {
        state.news[iso] = await api('GET', 'news/' + iso);
      } catch (e) {
        state.news[iso] = { error: e.message };
      }
      render();
    });
  }

  // One request for the whole world; tiles pick their country's alerts.
  async function loadAlerts() {
    if (!cfg.alerts) return;
    if (!state.gdacs) state.gdacs = { loading: true };
    try {
      state.gdacs = await api('GET', 'alerts');
    } catch (e) {
      state.gdacs = { error: e.message };
    }
    render();
  }
  const alertsFor = iso => (state.gdacs && state.gdacs.items || []).filter(e => e.countries.includes(iso));

  function refreshAll() {
    loadAlerts();
    state.selected.forEach(loadAdvice);
    state.selected.forEach(loadNews);
  }

  function add(iso) {
    if (!state.selected.includes(iso)) {
      state.selected.push(iso);
      saveCountries();
      loadAdvice(iso);
      loadNews(iso);
    }
    render();
  }

  function remove(iso) {
    state.selected = state.selected.filter(x => x !== iso);
    delete state.advice[iso];
    delete state.news[iso];
    state.open.delete(iso);
    saveCountries();
    render();
  }

  function setLang(lang) {
    state.lang = lang;
    store.set(KEY.lang, lang);
    if (state.me.loggedIn) api('PUT', 'me', { lang }).catch(() => {});
    render();
  }

  function setSource(source) {
    state.source = source;
    state.others = {};
    store.set(KEY.source, source);
    if (state.me.loggedIn) api('PUT', 'me', { source }).catch(() => {});
    state.selected.forEach(loadAdvice);
    state.selected.filter(iso => state.open.has(iso)).forEach(loadOthers);
    render();
  }

  // Levels from the two other governments, for comparison in an opened tile.
  function loadOthers(iso) {
    const others = SOURCES.filter(src => src !== state.source);
    state.others[iso] = Object.fromEntries(others.map(src => [src, { loading: true }]));
    others.forEach(async src => {
      try {
        state.others[iso][src] = await api('GET', 'advice/' + iso, null, { source: src });
      } catch (e) {
        state.others[iso][src] = { error: e.message };
      }
      if (state.others[iso]) render();
    });
  }

  function clearLocal() {
    store.del(KEY.countries);
    navigator.serviceWorker?.controller?.postMessage('clear-api-cache');
  }

  async function signOut() {
    await removeThisDevice();
    await api('POST', 'logout').catch(() => {});
    clearLocal();
    location.reload();
  }

  async function deleteAccount() {
    if (!confirm(t('deleteConfirm'))) return;
    try {
      await removeThisDevice();
      await api('DELETE', 'me');
      clearLocal();
      location.reload();
    } catch (e) {
      alert(errText(e.message));
    }
  }

  // ---------------------------------------------------------------- scoring
  const level = iso => (state.advice[iso] || {}).level || 0;
  const maxLevel = iso => (state.advice[iso] || {}).maxLevel || level(iso);
  const score = iso => maxLevel(iso) * 10 + level(iso);

  // ---------------------------------------------------------------- views
  const fmtDate = iso => iso ? new Date(iso).toLocaleDateString(LOCALE[state.lang], { day: 'numeric', month: 'long', year: 'numeric' }) : '';
  function ago(iso) {
    if (!iso) return '';
    const min = Math.round((new Date(iso) - Date.now()) / 60000);
    const rtf = new Intl.RelativeTimeFormat(LOCALE[state.lang], { numeric: 'auto' });
    if (Math.abs(min) < 60) return rtf.format(Math.min(min, -1), 'minute');
    if (Math.abs(min) < 48 * 60) return rtf.format(Math.round(min / 60), 'hour');
    return rtf.format(Math.round(min / 1440), 'day');
  }

  const ICONS = {
    countries: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/></svg>',
    alerts: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>',
    settings: '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/></svg>',
  };
  const hasTabs = () => state.view === 'app';
  const tabIds = () => (state.me.loggedIn ? ['countries', 'alerts', 'settings'] : ['countries', 'settings']);
  const TAB_LABEL = { countries: 'tabCountries', alerts: 'tabAlerts', settings: 'tabSettings' };

  // Same navigation twice: in the top bar on wide screens, as a bottom tab bar on phones and in the app.
  function tabs(where) {
    return h('nav', { class: 'tr-tabs tr-tabs-' + where, 'aria-label': t('menu') },
      tabIds().map(id => h('button', {
        type: 'button', 'aria-current': state.tab === id ? 'page' : null, 'data-tab': id,
        onclick: () => { state.tab = id; state.menuOpen = false; state.notice = ''; render(); window.scrollTo(0, 0); },
      }, h('span', { class: 'tr-ico', 'aria-hidden': 'true', innerHTML: ICONS[id] }),
        h('span', {}, t(TAB_LABEL[id])))));
  }

  function topBar() {
    const langs = h('div', { class: 'tr-lang', role: 'group', 'aria-label': t('language') },
      cfg.languages.map(l => h('button', {
        type: 'button', lang: l, 'aria-pressed': String(l === state.lang),
        onclick: () => l !== state.lang && setLang(l),
      }, l.toUpperCase())));

    const account = state.me.loggedIn && h('div', { class: 'tr-account' },
      h('button', {
        type: 'button', class: 'tr-ghost', 'aria-expanded': String(state.menuOpen), 'aria-haspopup': 'true',
        title: t('account'), onclick: () => { state.menuOpen = !state.menuOpen; render(); },
      }, h('span', { class: 'tr-avatar', 'aria-hidden': 'true' }, (state.me.email || '?')[0].toUpperCase()),
        h('span', { class: 'tr-sr' }, t('account'))),
      state.menuOpen && h('div', { class: 'tr-menu' },
        h('p', { class: 'tr-menu-email' }, state.me.email),
        canGuide() && h('button', { type: 'button', onclick: openGuide }, t('installMenu')),
        h('button', { type: 'button', onclick: startTour }, t('tourMenu')),
        h('button', { type: 'button', onclick: signOut }, t('signOut')),
        state.me.canDelete && h('button', { type: 'button', class: 'tr-danger', onclick: deleteAccount }, t('deleteAccount')),
        h('p', { class: 'tr-menu-version' }, cfg.brand + ' · ' + t('version', { v: cfg.version }))));

    return h('header', { class: 'tr-top' },
      h('div', { class: 'tr-brand' },
        h('span', { class: 'tr-mark', 'aria-hidden': 'true' }),
        h('span', { class: 'tr-name' }, cfg.brand)),
      h('div', { class: 'tr-actions' },
        hasTabs() && tabs('top'),
        state.installPrompt && !mobilePlatform() && h('button', { type: 'button', class: 'tr-ghost tr-install', onclick: install }, t('install')),
        langs, account));
  }

  function authView() {
    if (state.view === 'sent') {
      return h('section', { class: 'tr-auth' },
        h('h1', {}, t('sentTitle')),
        h('p', {}, t('sentText', { email: state.sentTo })),
        h('form', { class: 'tr-auth-form', novalidate: true, onsubmit: verifyCode },
          h('label', { for: 'tr-code' }, t('codeLabel')),
          h('input', {
            id: 'tr-code', name: 'code', type: 'text', inputmode: 'numeric', autocomplete: 'one-time-code',
            maxlength: '6', class: 'tr-code', value: state.code, oninput: e => { state.code = e.target.value; },
          }),
          state.authError && h('p', { class: 'tr-error', role: 'alert' }, state.authError),
          h('button', { type: 'submit', class: 'tr-primary' }, t('codeButton'))),
        h('button', { type: 'button', class: 'tr-ghost', onclick: () => { state.view = 'auth'; state.authError = ''; render(); } }, t('otherEmail')));
    }
    const form = h('form', { class: 'tr-auth-form', novalidate: true, onsubmit: requestLink },
      h('label', { for: 'tr-email' }, t('email')),
      h('input', { id: 'tr-email', name: 'email', type: 'email', required: true, autocomplete: 'email', value: state.email,
        oninput: e => { state.email = e.target.value; } }),
      h('input', { name: 'website', type: 'text', tabindex: '-1', autocomplete: 'off', class: 'tr-hp', 'aria-hidden': 'true' }),
      h('label', { class: 'tr-check' },
        h('input', { type: 'checkbox', name: 'consent', required: true, checked: state.consent,
          onchange: e => { state.consent = e.target.checked; } }),
        h('span', {}, t('consent'), ' ',
          cfg.privacyUrl && h('a', { href: cfg.privacyUrl, target: '_blank', rel: 'noopener' }, t('privacy')))),
      state.authError && h('p', { class: 'tr-error', role: 'alert' }, state.authError),
      h('button', { type: 'submit', class: 'tr-primary' }, t('sendLink')));
    return h('section', { class: 'tr-auth' },
      h('h1', {}, t('regTitle', { brand: cfg.brand })),
      h('p', { class: 'tr-lead' }, t('regText')),
      form);
  }

  async function requestLink(e) {
    e.preventDefault();
    const f = e.target;
    const email = f.email.value.trim();
    state.authError = !f.consent.checked ? errText('consent_required')
      : !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? errText('invalid_email') : '';
    if (state.authError) return render();
    const btn = f.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.textContent = t('sending');
    try {
      await api('POST', 'login', { email, consent: true, lang: state.lang, website: f.website.value });
      state.sentTo = email;
      state.view = 'sent';
    } catch (err) {
      state.authError = errText(err.message);
    }
    render();
  }

  async function verifyCode(e) {
    e.preventDefault();
    const code = state.code.replace(/\D/g, '');
    if (code.length !== 6) { state.authError = errText('invalid_code'); return render(); }
    try {
      await api('POST', 'login/verify', { email: state.sentTo, code });
      location.reload(); // fresh page = fresh session nonce
    } catch (err) {
      state.authError = errText(err.message);
      if (err.message === 'expired_token') state.code = '';
      render();
    }
  }

  // ---------------------------------------------------------------- notifications
  const pushSupported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const within = (promise, ms) => Promise.race([promise, new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), ms))]);
  const currentSubscription = () => within(navigator.serviceWorker.ready, 4000).then(reg => reg.pushManager.getSubscription());
  const keyBytes = b64 => Uint8Array.from(atob(b64.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - b64.length % 4) % 4)), c => c.charCodeAt(0));

  async function detectPush() {
    if (!pushSupported()) state.push = 'unsupported';
    else if (Notification.permission === 'denied') state.push = 'denied';
    else state.push = await currentSubscription().then(sub => (sub ? 'on' : 'off'), () => 'off');
    render();
  }

  async function enablePush() {
    state.push = 'busy'; state.notice = ''; render();
    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') { state.push = permission === 'denied' ? 'denied' : 'off'; return render(); }
      const { publicKey } = await api('GET', 'push/key');
      const reg = await within(navigator.serviceWorker.ready, 4000);
      const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) });
      state.me = await api('POST', 'push', sub.toJSON());
      state.push = 'on';
    } catch (e) {
      // Browser errors (push service unreachable, aborted) have no server code.
      state.push = 'off';
      state.notice = T.en.err[e.message] ? errText(e.message) : t('pushFailed');
    }
    render();
  }

  async function disablePush() {
    state.push = 'busy'; state.notice = ''; render();
    await removeThisDevice();
    state.me = await api('GET', 'me').catch(() => state.me);
    state.push = 'off';
    render();
  }

  // Also used on sign-out, so a shared device stops receiving someone else's alerts.
  async function removeThisDevice() {
    try {
      const sub = pushSupported() && await currentSubscription();
      if (!sub) return;
      await api('DELETE', 'push', { endpoint: sub.endpoint }).catch(() => {});
      await sub.unsubscribe();
    } catch { /* nothing registered on this device */ }
  }

  async function setEmailAlerts(on) {
    try {
      state.me = await api('PUT', 'me', { notifyEmail: on });
    } catch (e) {
      state.notice = errText(e.message);
    }
    render();
  }

  function switchControl(label, checked, onchange, disabled) {
    return h('button', {
      type: 'button', role: 'switch', class: 'tr-switch', 'aria-checked': String(checked), 'aria-label': label,
      disabled, onclick: () => onchange(!checked),
    }, h('span', { 'aria-hidden': 'true' }));
  }

  function alertsView() {
    const p = state.push;
    const pushText = p === 'unsupported' ? (isIos() ? t('pushIos') : t('pushUnsupported'))
      : p === 'denied' ? t('pushDenied') : p === 'on' ? t('pushOn') : p === 'off' ? t('pushOff') : '…';
    const n = state.selected.length;
    return h('div', { class: 'tr-app' },
      h('section', { class: 'tr-hero' },
        h('h1', {}, t('alertsTitle')),
        h('p', { class: 'tr-lead' }, t('alertsLead') + (cfg.alerts ? ' ' + t('alertsLeadGdacs') : ''))),
      h('section', { class: 'tr-panel' },
        h('div', { class: 'tr-setting' },
          h('div', {}, h('h2', {}, t('pushTitle')), h('p', { class: 'tr-muted' }, pushText)),
          ['on', 'off', 'busy'].includes(p) && switchControl(t('pushTitle'), p === 'on', on => (on ? enablePush() : disablePush()), p === 'busy')),
        p === 'unsupported' && canGuide() && h('button', { type: 'button', class: 'tr-ghost tr-small', onclick: openGuide }, t('howTo')),
        p === 'off' && h('p', { class: 'tr-muted tr-small-print' }, t('pushConsent')),
        state.me.pushDevices > 0 && h('p', { class: 'tr-muted tr-small-print' }, t('pushAccount', { n: state.me.pushDevices, email: state.me.email }))),
      h('section', { class: 'tr-panel' },
        h('div', { class: 'tr-setting' },
          h('div', {}, h('h2', {}, t('emailTitle')), h('p', { class: 'tr-muted' }, t('emailText', { email: state.me.email }))),
          switchControl(t('emailTitle'), !!state.me.notifyEmail, setEmailAlerts))),
      state.notice && h('p', { class: 'tr-notice', role: 'status' }, state.notice),
      h('p', { class: 'tr-muted tr-follows' }, n ? t('follows', { n, source: SOURCE[state.source] }) : t('followsNone')));
  }

  // --- country picker (combobox)
  let active = -1;
  function picker() {
    const input = h('input', {
      id: 'tr-search', type: 'text', autocomplete: 'off', placeholder: t('search'),
      role: 'combobox', 'aria-expanded': 'false', 'aria-controls': 'tr-suggest', 'aria-autocomplete': 'list',
      'aria-label': t('searchLabel'),
    });
    const list = h('ul', { id: 'tr-suggest', role: 'listbox', hidden: true });

    const suggest = () => {
      const q = input.value.trim().toLowerCase();
      list.replaceChildren();
      active = -1;
      if (q) {
        state.countries
          .filter(c => !state.selected.includes(c.iso3) &&
            (['en', 'de', 'nl'].some(l => c[l].toLowerCase().includes(q)) || c.iso3.toLowerCase() === q || c.iso2.toLowerCase() === q))
          .sort((a, b) => a[state.lang].localeCompare(b[state.lang], LOCALE[state.lang]))
          .slice(0, 8)
          .forEach((c, i) => list.append(h('li', {
            id: 'tr-opt-' + i, role: 'option', 'data-iso': c.iso3,
            onmousedown: ev => { ev.preventDefault(); choose(c.iso3); },
          }, c[state.lang])));
      }
      list.hidden = !list.children.length;
      input.setAttribute('aria-expanded', String(!list.hidden));
    };
    const mark = i => {
      const items = [...list.children];
      if (!items.length) return;
      active = (i + items.length) % items.length;
      items.forEach((li, n) => li.setAttribute('aria-selected', String(n === active)));
      input.setAttribute('aria-activedescendant', items[active].id);
    };
    const choose = iso => { input.value = ''; add(iso); document.getElementById('tr-search')?.focus(); };

    input.addEventListener('input', suggest);
    input.addEventListener('blur', () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); });
    input.addEventListener('keydown', e => {
      const items = [...list.children];
      if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
      else if (e.key === 'Enter' && items.length) { e.preventDefault(); choose(items[Math.max(active, 0)].dataset.iso); }
      else if (e.key === 'Escape') { input.value = ''; suggest(); }
    });

    const chips = h('ul', { class: 'tr-chips' }, state.selected.map(iso =>
      h('li', { class: 'lv' + maxLevel(iso) }, cname(iso),
        h('button', { type: 'button', 'aria-label': t('remove', { name: cname(iso) }), title: t('remove', { name: cname(iso) }), onclick: () => remove(iso) }, '×'))));

    return h('section', { class: 'tr-picker' },
      h('div', { class: 'tr-row' },
        h('div', { class: 'tr-search' }, input, list),
        h('button', { type: 'button', class: 'tr-primary', onclick: refreshAll, disabled: !state.selected.length }, t('refresh'))),
      chips);
  }

  function toggle(iso) {
    if (state.open.has(iso)) state.open.delete(iso);
    else {
      state.open.add(iso);
      if (!state.others[iso]) loadOthers(iso);
    }
    render();
  }

  function toggleAll() {
    const allOpen = state.selected.every(iso => state.open.has(iso));
    state.open = allOpen ? new Set() : new Set(state.selected);
    state.selected.filter(iso => state.open.has(iso) && !state.others[iso]).forEach(loadOthers);
    render();
  }

  // Compact tile; clicking it opens the full advice and news below.
  function card(iso) {
    const a = state.advice[iso] || { loading: true };
    const n = state.news[iso];
    const lv = a.level || 0;
    const open = state.open.has(iso);
    const newsItems = n && n.items ? n.items.length : 0;
    const gd = alertsFor(iso);
    const severe = gd.find(e => e.level !== 'green');
    const recent = a.updated && Date.now() - new Date(a.updated) < RECENT_DAYS * 864e5;

    const badges = h('span', { class: 'tr-badges' },
      severe && h('span', { class: 'tr-badge gd-' + severe.level, title: severe.title }, t('hazard')[severe.type] || severe.type),
      a.maxLevel > a.level && h('span', { class: 'tr-badge lv' + a.maxLevel }, t('parts', { level: t('level')[a.maxLevel] })),
      !(a.maxLevel > a.level) && a.regional && h('span', { class: 'tr-badge lv3' }, t('regionalBadge')),
      newsItems > 0 && h('span', { class: 'tr-badge' }, t('newsCount', { n: newsItems })),
      recent && h('span', { class: 'tr-badge' }, t('recentBadge')),
      a.error && h('span', { class: 'tr-badge' }, '!'));

    const head = h('h2', {},
      h('button', {
        type: 'button', id: 'tr-t-' + iso, class: 'tr-toggle',
        'aria-expanded': String(open), 'aria-controls': 'tr-b-' + iso, onclick: () => toggle(iso),
      },
      h('span', { class: 'tr-cname' }, cname(iso)),
      h('span', { class: 'tr-level' }, a.loading ? '…' : t('level')[lv]),
      badges,
      h('span', { class: 'tr-chev', 'aria-hidden': 'true' })));

    const card = h('article', { class: 'tr-card lv' + lv + (open ? ' is-open' : ''), 'aria-busy': a.loading ? 'true' : null }, head);
    if (!open) return card;

    const body = h('div', { class: 'tr-body', id: 'tr-b-' + iso });
    if (a.loading) body.append(h('p', { class: 'tr-muted' }, t('loading')));
    else if (a.error) body.append(h('p', { class: 'tr-error' }, errText(a.error)));
    else {
      if (!a.level) body.append(h('p', { class: 'tr-note' }, t('noLevel')));
      if (a.maxLevel > a.level) {
        body.append(h('p', { class: 'tr-note lv' + a.maxLevel }, t('partial', { level: t('level')[a.maxLevel] })));
      } else if (a.regional) {
        body.append(h('p', { class: 'tr-note' }, t('regionalUnknown')));
      }
      body.append(h('p', { class: 'tr-meta' },
        [a.updated && t('updated', { date: fmtDate(a.updated) }), t('source', { source: a.sourceName })].filter(Boolean).join(' · ')));
      // The raw data the level is based on, so users can check our reading of the source.
      // The government's own note on what changed, where the source publishes one.
      if (a.latest) body.append(h('p', { class: 'tr-latest' }, h('b', {}, t('latest') + ': '), a.latest));
      if (a.basis) body.append(h('p', { class: 'tr-meta tr-basis' }, h('b', {}, t('basis') + ': '), a.basis));
      const cmp = state.others[iso] || {};
      const verdict = consensus(a, cmp);
      if (verdict) body.append(h('p', { class: 'tr-consensus' }, verdict));
      body.append(h('div', { class: 'tr-compare' },
        h('span', { class: 'tr-muted' }, t('otherSources') + ':'),
        SOURCES.filter(src => src !== state.source).map(src => {
          const o = cmp[src] || { loading: true };
          const lvl = o.loading || o.error ? 0 : (o.maxLevel > o.level ? o.maxLevel : o.level) || 0;
          const label = o.loading ? '…' : o.error ? '—' : t('level')[o.level || 0]
            + (o.maxLevel > o.level ? ' / ▲ ' + t('level')[o.maxLevel] : o.regional ? ' / ▲' : '');
          return h('button', {
            type: 'button', class: 'tr-cmp lv' + lvl, onclick: () => setSource(src),
            title: t('followSource', { source: SOURCE[src] }) + (o.basis ? '\n' + t('basis') + ': ' + o.basis : ''),
          }, h('b', {}, SOURCE_SHORT[src]), ' ', label);
        })));
      body.append(h('div', { class: 'tr-summary' },
        h('h3', {}, t('summary')),
        h('p', {}, a.summary || ''),
        t('sourceLang')[state.source] && h('p', { class: 'tr-muted tr-small-print' }, t('inLanguage', { lang: t('sourceLang')[state.source] })),
        a.url && h('a', { href: a.url, target: '_blank', rel: 'noopener' }, t('fullAdvice'), ' ↗')));
    }

    if (cfg.alerts) {
      const g = state.gdacs || { loading: true };
      body.append(h('h3', {}, t('gdacsTitle')));
      if (g.loading) body.append(h('p', { class: 'tr-muted' }, '…'));
      else if (g.error) body.append(h('p', { class: 'tr-error' }, errText(g.error)));
      else if (!gd.length) body.append(h('p', { class: 'tr-muted' }, t('gdacsNone')));
      else {
        body.append(h('ul', { class: 'tr-news tr-gdacs' }, gd.map(e =>
          h('li', { class: 'gd-' + e.level }, h('a', { href: e.url, target: '_blank', rel: 'noopener' }, e.title),
            h('span', {}, [t('alertLevel')[e.level], t('hazard')[e.type] || e.type, ago(e.to || e.from)].filter(Boolean).join(' · '))))));
        if (state.lang !== 'en') body.append(h('p', { class: 'tr-muted tr-small-print' }, t('gdacsLang')));
      }
    }

    if (cfg.news && n) {
      body.append(h('h3', {}, t('newsTitle', { h: n.hours || 48 })));
      if (n.loading) body.append(h('p', { class: 'tr-muted' }, t('newsLoading')));
      else if (n.error) body.append(h('p', { class: 'tr-error' }, errText(n.error)));
      else if (!n.items.length) body.append(h('p', { class: 'tr-muted' }, t('newsNone')));
      else body.append(h('ul', { class: 'tr-news' }, n.items.map(i =>
        h('li', {}, h('a', { href: i.url, target: '_blank', rel: 'noopener nofollow' }, i.title),
          h('span', {}, [i.source, ago(i.date)].filter(Boolean).join(' · '))))));
    }
    card.append(body);
    return card;
  }

  // Most common country-wide level across all governments (ties go to the stricter one) and the strictest.
  function consensus(own, others) {
    const all = [[state.source, own], ...Object.entries(others)].filter(([, a]) => a && a.level);
    if (all.length < 3) return null;
    const count = {};
    all.forEach(([, a]) => { count[a.level] = (count[a.level] || 0) + 1; });
    const most = Object.keys(count).map(Number).sort((x, y) => count[y] - count[x] || y - x)[0];
    const max = Math.max(...all.map(([, a]) => a.maxLevel || a.level));
    const who = all.filter(([, a]) => (a.maxLevel || a.level) === max).map(([src]) => SOURCE_SHORT[src]).join(', ');
    return t('consensus', { n: all.length, most: t('level')[most], max: t('level')[max], who });
  }

  function summaryLine() {
    const n = state.selected.length;
    if (!n) return t('sumNone');
    const done = state.selected.filter(iso => state.advice[iso] && !state.advice[iso].loading);
    if (done.length < n) return t('sumLoading', { n });
    const elevated = done.filter(iso => maxLevel(iso) >= 2).length;
    return elevated ? t('sumAttention', { a: elevated, n }) : t('sumOk', { n });
  }

  function appView() {
    const sorted = [...state.selected].sort((x, y) => score(y) - score(x));
    return h('div', { class: 'tr-app' },
      h('section', { class: 'tr-hero' },
        h('h1', {}, summaryLine()),
        h('p', { class: 'tr-lead' }, t('tagline'))),
      picker(),
      sorted.length > 1 && h('div', { class: 'tr-gridbar' },
        h('button', { type: 'button', class: 'tr-ghost tr-small', onclick: toggleAll },
          state.selected.every(iso => state.open.has(iso)) ? t('collapseAll') : t('expandAll'))),
      h('section', { class: 'tr-grid', 'aria-live': 'polite' },
        sorted.length ? sorted.map(card) : h('div', { class: 'tr-empty' }, t('empty'))),
      state.updatedAt && h('p', { class: 'tr-muted tr-stamp' },
        t('updatedAt', { time: state.updatedAt.toLocaleTimeString(LOCALE[state.lang], { hour: '2-digit', minute: '2-digit' }) })),
      // The licence of the chosen source asks for attribution where its information is shown.
      h('footer', { class: 'tr-foot' },
        h('p', {}, cfg.credits[state.source], ' ', t('notEndorsed'), ' ',
          h('button', { type: 'button', class: 'tr-link', onclick: () => goTab('settings') }, t('allSources')))));
  }

  function goTab(id) {
    state.tab = id; state.menuOpen = false; state.notice = '';
    render();
    window.scrollTo(0, 0);
  }

  function sourcePicker() {
    return h('div', { class: 'tr-sources' },
      h('div', { class: 'tr-lang tr-src', role: 'group', 'aria-labelledby': 'tr-src-label' },
        SOURCES.map(src => h('button', {
          type: 'button', title: SOURCE[src], 'aria-pressed': String(src === state.source),
          onclick: () => src !== state.source && setSource(src),
        }, SOURCE_SHORT[src]))),
      h('span', { class: 'tr-muted tr-sources-name' }, SOURCE[state.source]));
  }

  function settingsView() {
    return h('div', { class: 'tr-app' },
      h('section', { class: 'tr-hero' },
        h('h1', {}, t('settingsTitle'))),
      h('section', { class: 'tr-panel' },
        h('h2', { id: 'tr-src-label' }, t('adviceFrom')),
        sourcePicker(),
        h('p', { class: 'tr-muted tr-small-print' }, t('adviceHint'))),
      h('section', { class: 'tr-panel tr-sources-panel' },
        h('h2', {}, t('sourcesTitle')),
        h('p', { class: 'tr-muted' }, t('footer', { source: SOURCE[state.source] }), cfg.news && ' ' + t('footerNews', { news: NEWS[cfg.news] })),
        h('ul', { class: 'tr-credits' }, SOURCES.map(src => h('li', {}, cfg.credits[src])),
          cfg.alerts && h('li', {}, cfg.alertsCredit),
          cfg.news === 'gdelt' && h('li', {}, 'News: The GDELT Project (gdeltproject.org).')),
        h('p', { class: 'tr-muted tr-small-print' }, t('notEndorsed'))),
      h('section', { class: 'tr-panel' },
        h('h2', {}, t('aboutTitle', { brand: cfg.brand })),
        h('p', { class: 'tr-muted' }, t('version', { v: cfg.version })),
        h('div', { class: 'tr-about-actions' },
          h('button', { type: 'button', class: 'tr-ghost', onclick: startTour }, t('tourMenu')),
          canGuide() && h('button', { type: 'button', class: 'tr-ghost', onclick: openGuide }, t('installMenu')),
          cfg.privacyUrl && h('a', { href: cfg.privacyUrl, target: '_blank', rel: 'noopener' }, t('privacy')))));
  }

  // ---------------------------------------------------------------- tour
  // Interactive introduction: on first use and from the account menu / settings. Highlights
  // one element per step; steps whose element is missing (no tiles yet) are left out.
  let guidePending = false;
  function tourSteps() {
    return [
      { id: 'welcome' },
      { id: 'search', sel: ['#tr-search'] },
      state.selected.length && { id: 'tile', sel: ['.tr-card'] },
      state.me.loggedIn && { id: 'alerts', sel: ['.tr-tabs-bottom [data-tab=alerts]', '.tr-tabs-top [data-tab=alerts]'] },
      { id: 'settings', sel: ['.tr-tabs-bottom [data-tab=settings]', '.tr-tabs-top [data-tab=settings]'] },
      state.me.loggedIn && { id: 'account', sel: ['.tr-account > button'] },
    ].filter(Boolean);
  }
  const shown = el => el.getClientRects().length > 0 && getComputedStyle(el).display !== 'none';
  const tourTarget = step => (step.sel || []).map(sel => [...root.querySelectorAll(sel)].find(shown)).find(Boolean) || null;

  function startTour() {
    state.menuOpen = false; state.guide = false; state.tab = 'countries'; state.tour = 0;
    window.scrollTo(0, 0);
    render();
  }

  function endTour() {
    state.tour = null;
    store.set(KEY.tour, 1);
    if (guidePending) { guidePending = false; state.guide = true; }
    render();
  }

  function tourLayer() {
    const steps = tourSteps();
    const i = Math.min(state.tour, steps.length - 1);
    const [title, text] = t('tour')[steps[i].id];
    const last = i === steps.length - 1;
    return h('div', { class: 'tr-tour', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'tr-tour-title' },
      h('div', { class: 'tr-tour-spot', 'aria-hidden': 'true' }),
      h('div', { class: 'tr-tour-bubble' },
        h('p', { class: 'tr-tour-step' }, t('tourStep', { i: i + 1, n: steps.length })),
        h('h2', { id: 'tr-tour-title' }, title.replace('{brand}', cfg.brand)),
        h('p', {}, text.replace('{brand}', cfg.brand)),
        h('div', { class: 'tr-tour-actions' },
          !last && h('button', { type: 'button', class: 'tr-ghost tr-small tr-tour-skip', onclick: endTour }, t('tourSkip')),
          i > 0 && h('button', { type: 'button', class: 'tr-ghost', onclick: () => { state.tour = i - 1; render(); } }, t('tourBack')),
          h('button', {
            type: 'button', class: 'tr-primary', id: 'tr-tour-next',
            onclick: () => { if (last) endTour(); else { state.tour = i + 1; render(); } },
          }, last ? t('tourDone') : t('tourNext')))));
  }

  // Positions the spotlight on the step's element and the bubble below (or above) it.
  function placeTour() {
    const layer = root.querySelector('.tr-tour');
    if (!layer) return;
    const steps = tourSteps();
    const el = tourTarget(steps[Math.min(state.tour, steps.length - 1)]);
    const spot = layer.querySelector('.tr-tour-spot');
    const bubble = layer.querySelector('.tr-tour-bubble');
    layer.classList.toggle('is-center', !el);
    if (!el) { spot.removeAttribute('style'); bubble.removeAttribute('style'); return; }
    let r = el.getBoundingClientRect();
    if (r.top < 80 || r.bottom > innerHeight - 120) {
      el.scrollIntoView({ block: 'center' });
      r = el.getBoundingClientRect();
    }
    const pad = 6, gap = 12;
    Object.assign(spot.style, { top: r.top - pad + 'px', left: r.left - pad + 'px', width: r.width + 2 * pad + 'px', height: r.height + 2 * pad + 'px' });
    const width = Math.min(340, innerWidth - 24);
    bubble.style.width = width + 'px';
    const bh = bubble.offsetHeight;
    const top = r.bottom + pad + gap + bh < innerHeight - 8 ? r.bottom + pad + gap : Math.max(8, r.top - pad - gap - bh);
    bubble.style.top = top + 'px';
    bubble.style.left = Math.min(Math.max(12, r.left + r.width / 2 - width / 2), innerWidth - width - 12) + 'px';
  }
  window.addEventListener('resize', placeTour);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && state.tour !== null) endTour(); });

  function render() {
    const focusId = document.activeElement && root.contains(document.activeElement) ? document.activeElement.id : null;
    const searchValue = document.getElementById('tr-search')?.value || '';
    root.lang = state.lang;
    root.removeAttribute('data-loading');
    const main = state.view === 'loading' ? h('p', { class: 'tr-muted' }, t('signingIn'))
      : state.view !== 'app' ? authView()
      : state.tab === 'alerts' && state.me.loggedIn ? alertsView()
      : state.tab === 'settings' ? settingsView() : appView();
    root.classList.toggle('has-tabs', hasTabs());
    root.replaceChildren(...[
      topBar(),
      !navigator.onLine && h('p', { class: 'tr-offline', role: 'status' }, t('offline')),
      main,
      state.guide && canGuide() && guideSheet(),
      hasTabs() && tabs('bottom'),
      state.tour !== null && state.view === 'app' && tourLayer(),
    ].filter(Boolean));
    if (state.tour !== null) {
      placeTour();
      document.getElementById('tr-tour-next')?.focus({ preventScroll: true });
    }
    const search = document.getElementById('tr-search');
    if (focusId) document.getElementById(focusId)?.focus();
    if (search && searchValue) {
      search.value = searchValue;
      if (focusId === 'tr-search') search.dispatchEvent(new Event('input'));
    }
  }

  // ---------------------------------------------------------------- install instructions
  const isStandalone = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  // Phones and tablets only; laptops, MacBooks and Chromebooks (CrOS) get no instructions.
  // iPadOS reports itself as a Mac, so touch support tells an iPad from a MacBook.
  function mobilePlatform() {
    const ua = navigator.userAgent;
    if (/CrOS/.test(ua)) return null;
    if (/iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) return 'ios';
    if (/Android/.test(ua)) return 'android';
    return null;
  }
  const canGuide = () => !isStandalone() && mobilePlatform() !== null;
  const GUIDE_SNOOZE_DAYS = 14;

  const GUIDE_ICONS = {
    share: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>',
    add: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M12 8v8M8 12h8"/></svg>',
    menu: '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>',
  };

  // Text with {share}/{add}/{menu} icon placeholders and {brand}.
  function richText(text) {
    return text.replace(/\{brand\}/g, cfg.brand).split(/(\{share\}|\{add\}|\{menu\})/).map(part => {
      const m = part.match(/^\{(share|add|menu)\}$/);
      return m ? h('span', { class: 'tr-inline-ico', 'aria-hidden': 'true', innerHTML: GUIDE_ICONS[m[1]] }) : part;
    });
  }

  function openGuide() { state.guide = true; state.menuOpen = false; render(); }
  function closeGuide() {
    state.guide = false;
    store.set(KEY.guide, Date.now() + GUIDE_SNOOZE_DAYS * 864e5);
    render();
  }

  function guideSheet() {
    const platform = mobilePlatform();
    const oneTap = platform === 'android' && state.installPrompt;
    const steps = platform === 'ios' ? t('iosSteps') : t('androidSteps');
    return h('aside', { class: 'tr-sheet', role: 'dialog', 'aria-labelledby': 'tr-sheet-title' },
      h('div', { class: 'tr-sheet-head' },
        h('img', { src: cfg.icon, alt: '', width: 48, height: 48, class: 'tr-sheet-icon' }),
        h('h2', { id: 'tr-sheet-title' }, t('installTitle', { brand: cfg.brand })),
        h('button', { type: 'button', class: 'tr-sheet-close', 'aria-label': t('notNow'), onclick: closeGuide }, '×')),
      h('p', { class: 'tr-sheet-lead' }, t('installLead', { brand: cfg.brand })),
      oneTap ? h('p', {}, richText(t('androidOneTap')))
        : h('ol', { class: 'tr-steps' }, steps.map(step => h('li', {}, h('span', {}, richText(step))))),
      platform === 'ios' && h('p', { class: 'tr-muted tr-small-print' }, t('iosNote')),
      h('div', { class: 'tr-sheet-actions' },
        h('button', { type: 'button', class: 'tr-ghost', onclick: closeGuide }, t('notNow')),
        oneTap && h('button', { type: 'button', class: 'tr-primary', onclick: install }, t('installNow'))));
  }

  // ---------------------------------------------------------------- PWA
  function install() {
    state.installPrompt.prompt();
    state.installPrompt.userChoice.then(choice => {
      if (choice.outcome === 'accepted') state.guide = false;
    }).finally(() => { state.installPrompt = null; render(); });
  }
  window.addEventListener('appinstalled', () => { state.guide = false; store.set(KEY.guide, Date.now() + 3650 * 864e5); render(); });
  window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); state.installPrompt = e; render(); });
  window.addEventListener('online', render);
  window.addEventListener('offline', render);
  document.addEventListener('click', e => {
    if (state.menuOpen && !e.target.closest('.tr-account')) { state.menuOpen = false; render(); }
  });
  // Sign-in link opened in the tab that already shows the app: only the hash changes.
  window.addEventListener('hashchange', () => { if (/tr-login=/.test(location.hash)) location.reload(); });
  if ('serviceWorker' in navigator && cfg.worker) {
    navigator.serviceWorker.register(cfg.worker, { scope: '/' }).catch(() => {});
  }

  // ---------------------------------------------------------------- start
  async function start() {
    if (isStandalone() && cfg.appUrl && !new URLSearchParams(location.search).has('tr_app')) {
      location.replace(cfg.appUrl + location.hash);
      return;
    }
    render();
    // First visit on a phone or tablet: offer install instructions (not again for a while after "Not now").
    // On first use the tour comes first; the instructions follow when it closes.
    const wantGuide = canGuide() && Date.now() > (store.get(KEY.guide, 0) || 0);
    const token = (location.hash.match(/tr-login=([a-f0-9]{64})/) || [])[1];
    if (token) {
      history.replaceState(null, '', location.pathname + location.search);
      try {
        await api('POST', 'login/verify', { token });
        location.reload(); // fresh page = fresh session nonce
        return;
      } catch (e) {
        state.authError = errText(e.message);
      }
    }

    const [countries, me] = await Promise.all([
      fetch(cfg.countries).then(r => r.json()).catch(() => []),
      api('GET', 'me').catch(() => ({ loggedIn: false, registrationRequired: true, offline: true })),
    ]);
    state.countries = countries;
    state.me = me;

    const localSource = SOURCES.includes(store.get(KEY.source)) ? store.get(KEY.source) : null;
    state.source = localSource || (SOURCES.includes(me.source) ? me.source : defaultSource());
    if (store.get(KEY.source) && !localSource) store.set(KEY.source, state.source); // e.g. a source that was removed
    if (me.loggedIn && me.source !== state.source) api('PUT', 'me', { source: state.source }).catch(() => {});

    if (me.loggedIn) {
      if (!store.get(KEY.lang) && cfg.languages.includes(me.lang)) state.lang = me.lang;
      const merged = [...new Set([...(me.countries || []), ...state.selected])];
      const changed = merged.length !== (me.countries || []).length;
      state.selected = merged;
      store.set(KEY.countries, merged);
      if (changed) saveCountries();
      state.view = 'app';
    } else if (me.offline && state.selected.length) {
      state.view = 'app'; // offline: show what we have
    } else {
      state.view = me.registrationRequired ? 'auth' : 'app';
    }
    state.selected = state.selected.filter(byIso);
    render();

    if (wantGuide && state.view === 'app' && !store.get(KEY.tour)) guidePending = true;
    else if (wantGuide) setTimeout(() => { state.guide = true; render(); }, 1500);

    if (state.view === 'app') {
      if (!store.get(KEY.tour)) setTimeout(startTour, 800);
      if (state.me.loggedIn) detectPush();
      refreshAll();
      setInterval(refreshAll, 60 * 60 * 1000);
    }
  }

  start();
})();
