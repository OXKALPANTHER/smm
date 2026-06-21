# Royal SMM — Database + Free Deployment Guide

## A. Create the database in Supabase (free)

1. Go to https://supabase.com → sign in → **New project** (pick a region near you, set a strong DB password — **save it**).
2. Wait ~2 min for the project to finish provisioning.
3. Left sidebar → **SQL Editor** → **+ New query**.
4. Open the file **`supabase_schema.sql`** from this project, **copy the WHOLE file**, paste it in, click **Run** (▶, bottom right).
   - ✅ You should see "Success. No rows returned."
   - ⚠️ Do **NOT** paste `database.sql` — that one is MySQL and will fail with
     `syntax error at or near "NOT"`.
5. Left sidebar → **Table Editor** → schema **public** → you should now see
   `users`, `orders`, `transactions`, ... (refresh the page if empty).

> Default admin login created by the script: **admin / Admin@123** (change it after first login).

### Get your connection details
Project → **Settings** (gear) → **Database** → **Connection info / Connection string** →
choose the **Session pooler** tab. You'll get:
`host`, `port` (5432), `database` (postgres), `user` (looks like `postgres.abcdxyz`), and your password.

---

## B. Point the app at Supabase

The app auto-selects the database from environment variables (it stays on local
SQLite until you set these). Set:

```
DB_DRIVER=pgsql
DB_HOST=aws-0-xxxx.pooler.supabase.com
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.xxxxxxxxxxxx
DB_PASS=your-supabase-db-password
```

- **Locally (Git Bash):** `export DB_DRIVER=pgsql DB_HOST=... DB_PORT=5432 DB_NAME=postgres DB_USER=... DB_PASS=...` then `php -S 127.0.0.1:8088`.
- **On a host:** add them in the host's *Environment Variables* settings (see below).

No code changes needed — `config.php` reads these at runtime.

---

## C. Deploy for free

The app needs: **PHP**, **outbound HTTPS** (to the Boost API + Supabase), and a DB.
Recommended: **Supabase (DB) + Render (app)**. A `Dockerfile` is already included.

### Option 1 — Render (recommended, truly free)
1. Push this folder to a **GitHub** repo.
2. https://render.com → **New +** → **Web Service** → connect the repo.
3. Render detects the `Dockerfile`. Instance type: **Free**.
4. **Environment** → add the 6 `DB_*` variables from section B.
5. **Create Web Service**. First build takes a few minutes; you get a public
   `https://your-app.onrender.com` URL.
   - Note: the free tier **sleeps after ~15 min idle** (first hit then takes ~30s to wake).

### Option 2 — Fly.io (free allowance)
1. Install flyctl, `fly launch` in this folder (it uses the Dockerfile, don't deploy yet).
2. `fly secrets set DB_DRIVER=pgsql DB_HOST=... DB_PORT=5432 DB_NAME=postgres DB_USER=... DB_PASS=...`
3. `fly deploy`.

### Option 3 — Koyeb / Railway
Same idea: connect the GitHub repo, it builds the Dockerfile, add the `DB_*`
env vars. (Railway gives trial credits rather than a permanent free tier.)

### ⚠️ About classic free PHP hosts (000webhost, InfinityFree, etc.)
They're the easiest *looking* option but usually:
- only give **MySQL** (not Postgres) — you'd need a MySQL schema, and
- **block outbound connections**, which breaks the live Boost API + Supabase.

So they are **not recommended** for this app. Use a Docker host above instead.

---

## D. Quick checklist
- [ ] `supabase_schema.sql` ran successfully (tables visible in Table Editor)
- [ ] 6 `DB_*` env vars set on the host
- [ ] App opens, you can log in as `admin / Admin@123`
- [ ] Placing/viewing a service loads live prices (proves outbound works)
- [ ] Change the admin password
