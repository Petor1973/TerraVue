/* Travel Risk app. Vanilla JS, no build step. Config in window.TRAVEL_RISK (see Frontend.php). */
(() => {
  'use strict';

  const cfg = window.TRAVEL_RISK;
  const root = document.getElementById('travel-risk-app');
  if (!cfg || !root) return;

  // ---------------------------------------------------------------- texts
  const T = {
    en: {
      tagline: 'Travel advice and security news for the countries where your people work.',
      language: 'Language', search: 'Add a country, e.g. Saudi Arabia', searchLabel: 'Search country',
      refresh: 'Refresh', remove: 'Remove {name}', install: 'Install app',
      empty: 'No countries yet. Type a country above to see its travel advice and recent news.',
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
      footer: 'Travel advice: {source}. News: {news}, found automatically with security keywords in English-language media; not every item is relevant. Advice is normalised to four levels; always check the official advice for regional details.',
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
        offline: 'No connection.',
        default: 'Source not reachable. Try again later.',
      },
    },
    de: {
      tagline: 'Reisehinweise und Sicherheitsnachrichten für die Länder, in denen Ihre Leute arbeiten.',
      language: 'Sprache', search: 'Land hinzufügen, z. B. Saudi-Arabien', searchLabel: 'Land suchen',
      refresh: 'Aktualisieren', remove: '{name} entfernen', install: 'App installieren',
      empty: 'Noch keine Länder. Geben Sie oben ein Land ein, um Reisehinweise und aktuelle Nachrichten zu sehen.',
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
      footer: 'Reisehinweise: {source}. Nachrichten: {news}, automatisch über Sicherheitsbegriffe in englischsprachigen Medien gefunden; nicht jede Meldung ist relevant. Hinweise sind auf vier Stufen vereinheitlicht; prüfen Sie für regionale Details immer den offiziellen Hinweis.',
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
        offline: 'Keine Verbindung.',
        default: 'Quelle nicht erreichbar. Bitte später erneut versuchen.',
      },
    },
    nl: {
      tagline: 'Reisadvies en veiligheidsnieuws voor de landen waar jouw mensen werken.',
      language: 'Taal', search: 'Land toevoegen, bv. Saoedi-Arabië', searchLabel: 'Land zoeken',
      refresh: 'Vernieuwen', remove: '{name} verwijderen', install: 'App installeren',
      empty: 'Nog geen landen. Typ hierboven een land om het reisadvies en recent nieuws te zien.',
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
      footer: 'Reisadvies: {source}. Nieuws: {news}, automatisch gezocht op veiligheidstrefwoorden in Engelstalige media; niet elk bericht is relevant. Adviezen zijn omgezet naar vier niveaus; controleer voor regionale details altijd het officiële advies.',
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
        offline: 'Geen verbinding.',
        default: 'Bron niet bereikbaar. Probeer het later opnieuw.',
      },
    },
  };
  const LOCALE = { en: 'en-GB', de: 'de-DE', nl: 'nl-NL' };
  const SOURCE = { en: 'FCDO (UK)', de: 'Auswärtiges Amt', nl: 'Ministerie van Buitenlandse Zaken' };
  const NEWS = { gdelt: 'GDELT', google: 'Google News' };

  // ---------------------------------------------------------------- state
  const KEY = { lang: 'travel-risk.lang', countries: 'travel-risk.countries' };
  const store = {
    get(k, fallback) { try { const v = localStorage.getItem(k); return v === null ? fallback : JSON.parse(v); } catch { return fallback; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* private mode */ } },
    del(k) { try { localStorage.removeItem(k); } catch { /* ignore */ } },
  };

  const state = {
    lang: cfg.languages.includes(store.get(KEY.lang)) ? store.get(KEY.lang) : cfg.languages[0],
    countries: [],          // [{iso3, en, de, nl, ...}]
    selected: store.get(KEY.countries, []),
    advice: {},             // iso -> {loading} | data | {error}
    news: {},               // iso -> {loading} | data | {error}
    me: { loggedIn: false, registrationRequired: false },
    view: 'loading',        // loading | auth | sent | app
    open: new Set(),        // countries shown with full details
    email: '', consent: false, sentTo: '', authError: '', menuOpen: false, installPrompt: null, updatedAt: null,
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
      state.advice[iso] = await api('GET', 'advice/' + iso, null, { lang: state.lang });
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

  function refreshAll() {
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
    state.selected.forEach(loadAdvice); // advice source depends on language
    render();
  }

  function clearLocal() {
    store.del(KEY.countries);
    navigator.serviceWorker?.controller?.postMessage('clear-api-cache');
  }

  async function signOut() {
    await api('POST', 'logout').catch(() => {});
    clearLocal();
    location.reload();
  }

  async function deleteAccount() {
    if (!confirm(t('deleteConfirm'))) return;
    try {
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
        h('button', { type: 'button', onclick: signOut }, t('signOut')),
        state.me.canDelete && h('button', { type: 'button', class: 'tr-danger', onclick: deleteAccount }, t('deleteAccount'))));

    return h('header', { class: 'tr-top' },
      h('div', { class: 'tr-brand' },
        h('span', { class: 'tr-mark', 'aria-hidden': 'true' }),
        h('span', { class: 'tr-name' }, cfg.brand)),
      h('div', { class: 'tr-actions' },
        state.installPrompt && h('button', { type: 'button', class: 'tr-ghost tr-install', onclick: install }, t('install')),
        langs, account));
  }

  function authView() {
    if (state.view === 'sent') {
      return h('section', { class: 'tr-auth' },
        h('h1', {}, t('sentTitle')),
        h('p', {}, t('sentText', { email: state.sentTo })),
        h('button', { type: 'button', class: 'tr-ghost', onclick: () => { state.view = 'auth'; render(); } }, t('otherEmail')));
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
    if (state.open.has(iso)) state.open.delete(iso); else state.open.add(iso);
    render();
  }

  function toggleAll() {
    const allOpen = state.selected.every(iso => state.open.has(iso));
    state.open = allOpen ? new Set() : new Set(state.selected);
    render();
  }

  // Compact tile; clicking it opens the full advice and news below.
  function card(iso) {
    const a = state.advice[iso] || { loading: true };
    const n = state.news[iso];
    const lv = a.level || 0;
    const open = state.open.has(iso);
    const newsItems = n && n.items ? n.items.length : 0;

    const badges = h('span', { class: 'tr-badges' },
      a.maxLevel > a.level && h('span', { class: 'tr-badge lv' + a.maxLevel }, t('parts', { level: t('level')[a.maxLevel] })),
      newsItems > 0 && h('span', { class: 'tr-badge' }, t('newsCount', { n: newsItems })),
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
      }
      body.append(h('p', { class: 'tr-meta' },
        [a.updated && t('updated', { date: fmtDate(a.updated) }), t('source', { source: a.sourceName })].filter(Boolean).join(' · ')));
      body.append(h('div', { class: 'tr-summary' },
        h('h3', {}, t('summary')),
        h('p', {}, a.summary || ''),
        a.url && h('a', { href: a.url, target: '_blank', rel: 'noopener' }, t('fullAdvice'), ' ↗')));
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
      h('footer', { class: 'tr-foot' }, t('footer', { source: SOURCE[state.lang], news: NEWS[cfg.news] || '—' })));
  }

  function render() {
    const focusId = document.activeElement && root.contains(document.activeElement) ? document.activeElement.id : null;
    const searchValue = document.getElementById('tr-search')?.value || '';
    root.lang = state.lang;
    root.removeAttribute('data-loading');
    const main = state.view === 'loading' ? h('p', { class: 'tr-muted' }, t('signingIn'))
      : state.view === 'app' ? appView() : authView();
    root.replaceChildren(...[
      topBar(),
      !navigator.onLine && h('p', { class: 'tr-offline', role: 'status' }, t('offline')),
      main,
    ].filter(Boolean));
    const search = document.getElementById('tr-search');
    if (focusId) document.getElementById(focusId)?.focus();
    if (search && searchValue) {
      search.value = searchValue;
      if (focusId === 'tr-search') search.dispatchEvent(new Event('input'));
    }
  }

  // ---------------------------------------------------------------- PWA
  function install() {
    state.installPrompt.prompt();
    state.installPrompt.userChoice.finally(() => { state.installPrompt = null; render(); });
  }
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
    const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    if (standalone && cfg.appUrl && !new URLSearchParams(location.search).has('tr_app')) {
      location.replace(cfg.appUrl + location.hash);
      return;
    }
    render();
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

    if (state.view === 'app') {
      refreshAll();
      setInterval(refreshAll, 60 * 60 * 1000);
    }
  }

  start();
})();
