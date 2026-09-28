=== Terravue – Travel Risk Monitor ===
Requires at least: 6.4
Requires PHP: 8.0
Stable tag: 0.11.1
License: Proprietary

Official travel advice and disaster alerts per country, as an installable web app (PWA).

== Description ==

Users pick the countries they care about and see, per country:

* the official travel advice, normalised to four levels (normal / caution / essential travel only / do not travel),
  including a warning when parts of the country have a stricter level;
* the government's own note on its latest update, where the source publishes one;
* current disaster alerts from GDACS (earthquakes, cyclones, floods, volcanoes, wildfires), with a badge
  on the country tile for orange and red alerts;
* where parts of the country have stricter advice: a list of those regions with their level, and the
  government's own map (UK, NL), served as a copy from this site;
* optionally, recent security-related news (GDELT; off by default).

Changes: a tab with all changes in the travel advice worldwide (levels, updates) and new orange/red
disaster alerts of the last 7 days, and an optional daily world overview by push and/or e-mail at a
time each user chooses.

Users choose whose government advice they follow (usually their nationality or employer): the
Netherlands, the United Kingdom, Germany, the United States or Canada. This is
independent of the interface language; the default comes from the browser's region setting, never from
the device location. An opened country shows the other four governments' levels and a consensus (most common level and
the strictest, with the governments that give it).
The interface is English by default, with German and Dutch.

A short interactive tour explains the app on first use (and again from the account menu or Settings).
Users register once with their e-mail address (sign-in link or 6-digit code, no password, double opt-in).
Their countries and language are saved to their account and on the device, so the app also works offline.

On phones and tablets (not on laptops) visitors see short instructions to install the app on their
home screen: iPhone/iPad via Share → Add to Home Screen, Android with a one-tap Install button where the
browser supports it.

Notifications: users can turn on push notifications (per device) and/or e-mail. The advice for followed
countries is checked hourly; when the level changes, or GDACS issues an orange or red alert for one of
their countries, they get a message in their own language.
Push works in current Chrome, Edge, Firefox and Safari; on iPhone/iPad only in the app added to the
home screen (iOS 16.4+).

== Installation ==

1. Upload the zip via Plugins > Add New > Upload Plugin and activate.
2. Create a page with the shortcode `[travel_risk]`. That page becomes the app (and the PWA start page).
3. Settings > Privacy: set a privacy policy page. The suggested text for this plugin is available there.
4. Make sure the site sends e-mail reliably (SMTP plugin) and runs on HTTPS (required for the PWA and push).
   For reliable notifications, replace WP-Cron by a server cron job (see the settings page).
5. Admin menu Terravue (your product name): Settings (name, colours, disaster alerts, news), Notifications (status of the
   hourly check, test notifications) and Sources (licences, test all sources live for one country).

== Privacy ==

Stored per user: e-mail address, time of consent, chosen countries, language, chosen advice source; and only if the user turns
them on: push subscriptions per device and the e-mail notification preference. Users can delete their
account in the app. The plugin registers with Tools > Export / Erase Personal Data. External services
receive country names/codes only (GDACS: nothing; the server reads one public feed). The front end loads no third-party fonts, scripts or trackers.

== Data sources ==

Attribution for each source is shown in the app footer. The app is not affiliated with or endorsed by any government.

* UK – GOV.UK Content API: contains public sector information licensed under the Open Government Licence v3.0.
* United States – Department of State travel advisories (RSS): public domain; attribution given.
* Canada – Travel Advice and Advisories (open data): Open Government Licence – Canada.
* Germany – Auswärtiges Amt open data: check the terms before commercial use.
* Netherlands – Ministerie van Buitenlandse Zaken open data: check the terms before commercial use.
* GDACS (European Commission and United Nations) – disaster alerts: free use with attribution; check the terms.
* GDELT Project – news (optional). Google News RSS is optional and for personal, non-commercial use only
  (not for a business or a team at work).
