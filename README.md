# Privatus Analytics for WonderCMS

**Real visitor numbers for your WonderCMS site. No cookies, no cookie pop-up, no bots.**

You want to know if people visit your WonderCMS site. But Google Analytics uses
cookies, so in many countries you must show a cookie pop-up first (the box that
asks visitors to say yes to cookies). Many people click no, so they never show
up in your numbers. Bots add fake visits on top.

This plugin adds the Privatus Analytics script to every page of your WonderCMS
site. You paste your site ID once in the settings panel. Your own visits are not
counted while you are logged in, unless you turn that off. The script sets no
cookies and keeps no IP addresses. Bots are filtered out before they are
counted.

So you see how many real people came, which pages they looked at and where they
came from, on one simple dashboard. A report comes to your inbox each week, and
you can set alerts for when traffic jumps or drops. And you don't need a cookie
pop-up for your analytics.

**Free plan, no card:** 10,000 events a month (an event is a page view or an action you track) on up to 3 sites. [Start free at privatusanalytics.com](https://privatusanalytics.com).

- **No cookies.** No cookies and no hidden IDs on your visitors' devices, and no IP
  addresses are kept. Laws differ by country, so check your own legal advice.
- **Real people, not bots.** Known bots, data centers and spam visits are left
  out on every plan.
- **Small and fast.** The script is about 3 KB when compressed.

## Requirements

- WonderCMS 3.4 or later (tested on 3.4.3 and 3.6.0).
- A theme that prints `<?= $Wcms->css() ?>` inside `<head>`. All standard themes do.
- A Privatus Analytics site ID. It starts with `pa_`. You find it in Privatus
  Analytics under Site settings, Tracking.

## Install

From the settings panel:

1. Log in, open **Settings** and go to the **Plugins** tab.
2. Find **Privatus Analytics** and click **Install**.

If it is not in the list yet, paste this address into the custom module box at
the bottom of the Plugins tab, then install it:

```text
https://raw.githubusercontent.com/Privatus-Analytics/wondercms-privatus-analytics/main/wcms-modules.json
```

By hand: download the
[main branch zip](https://github.com/Privatus-Analytics/wondercms-privatus-analytics/archive/main.zip),
unzip it into `plugins/` and rename the folder to `wondercms-privatus-analytics`.

## Setup

1. Open **Settings** and go to the **Menu** tab.
2. Scroll down to **Privatus Analytics**.
3. Paste your site ID, pick the extra tracking you want and click
   **Save Privatus Analytics settings**.

Until you save a valid site ID, the plugin adds nothing to your pages.

## Settings

| Setting | What it does |
| --- | --- |
| **Site ID** | Your Privatus Analytics site ID, like `pa_7Q2K9XH3AB`. Leave it empty to turn tracking off. A value that does not look like a site ID is not saved. |
| **Time on page and how far people scroll** | Loads the `engage` module. |
| **Clicks on outside links, file downloads, forms people send and missing pages (404)** | Loads the `auto` module. |
| **Page speed (Web Vitals)** | Loads the `vitals` module. |
| **Click maps** | Loads the `clicks` module. |
| **Short surveys on your site** | Loads the `ask` module. |
| **Don't count my own visits while I am logged in** | On by default. When you are logged in, you get no script. |

The five extra tracking boxes are unticked until you tick them. With all five
ticked, the plugin adds this to the `<head>` of each page:

```html
<script defer src="https://privatusstats.com/js/pa.js" data-site="pa_YOURSITEID" data-modules="engage,auto,vitals,clicks,ask"></script>
```

## External service

After you save a site ID, every page loads `https://privatusstats.com/js/pa.js`
in the visitor's browser. The script sends the page address (query parameters
are removed except campaign parameters), the referrer, the browser language, the
screen width, the time zone name and event names to
`https://privatusstats.com/api/event`. The visitor's IP address is read in
memory to work out a rough country and to count unique visits, and it is not
stored.

* Terms of service: https://privatusanalytics.com/legal/terms
* Privacy policy: https://privatusanalytics.com/legal/privacy

## Development

```sh
php tests/PluginTest.php          # tag builder and settings helpers
./scripts/verify-docker.sh 3.6.0  # real WonderCMS in Docker: log in, save, check the page
```

## License

MIT. See `LICENSE`.
