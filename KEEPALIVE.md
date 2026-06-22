# Keeping Royal SMM awake (no sleep)

Free hosts (Render, Fly.io, etc.) put your app to **sleep** after a period with no
traffic, so the first visit afterwards is slow (cold start). There are two layers in
place — use **both**:

## 1. In-app heartbeat (already built in)
While anyone has the site open, the page pings `ping.php` every 4 minutes
(`includes/pwa.php`). This keeps the host warm **during active use** — but it can't help
when nobody has a tab open (e.g. overnight).

## 2. External uptime monitor (the reliable fix — do this once)
An external monitor pings your app on a schedule so it never goes idle. **UptimeRobot**
has a free plan that's perfect for this:

1. Create a free account at <https://uptimerobot.com>.
2. **+ Add New Monitor**.
3. Monitor Type: **HTTP(s)**.
4. Friendly Name: `Royal SMM`.
5. URL: `https://YOUR-DOMAIN/ping.php`  ← replace with your real domain.
6. Monitoring Interval: **5 minutes**.
7. Save.

That's it. UptimeRobot will hit `ping.php` every 5 minutes, 24/7, so the app stays awake.
`ping.php` does no database/session work, so these pings are extremely cheap.

> Alternatives that work the same way: cron-job.org, BetterStack/Better Uptime, Pingdom,
> or a GitHub Action on a schedule curling `ping.php`. Any one of them is enough — you
> only need a single monitor.

### Note on Render specifically
Render free **web services** sleep after ~15 min idle; a 5-minute monitor keeps them up.
If you later move to a paid instance or a "Background Worker"/always-on plan, the monitor
is no longer required (but does no harm).
