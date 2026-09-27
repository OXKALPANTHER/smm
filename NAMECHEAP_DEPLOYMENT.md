# Royal SMM on Namecheap Shared Hosting

This guide assumes a Namecheap Shared Hosting account with cPanel. It uses the clean deployment ZIP, MySQL/MariaDB, PHP, and Brevo HTTPS email.

## What you need before starting

- Your Namecheap cPanel login
- A domain or subdomain pointed to this hosting account
- Your FastWay API key
- A Brevo API key and verified sender email for real email delivery
- A strong administrator password

The clean package contains no old InfinityFree credentials, no Render credentials, and no local database. You add your own values during setup.

## 1. Download and upload the clean package

1. Download `royal-smm-namecheap-clean.zip`.
2. Sign in to Namecheap → **Hosting List** → **Go to cPanel**.
3. Open **File Manager**.
4. Open the document root for your domain:
   - Primary domain: usually `public_html/`
   - Addon domain/subdomain: use the document root shown in cPanel → Domains.
5. Upload the ZIP.
6. Extract it in that document root.
7. If extraction creates a nested `htdocs` folder, move the contents of that folder into the actual document root. The document root must contain `index.php`, `config.php`, `login.php`, and `includes/` directly.
8. Confirm that `.htaccess` is present. Enable **Show Hidden Files** in File Manager.

Do not upload the previous InfinityFree `config.php` or old `.env` file.

## 2. Create the MySQL database

1. In cPanel, open **MySQL Databases**.
2. Create a database, for example `royal_smm`.
3. Create a database user with a long random password.
4. Add the user to the database.
5. Grant **ALL PRIVILEGES**.
6. Write down the final prefixed values shown by cPanel. Namecheap commonly changes them to values such as:

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpaneluser_royal_smm
DB_USER=cpaneluser_royal_user
DB_PASS=your-database-password
```

Use the exact cPanel-prefixed names, not the short names you initially typed.

## 3. Import the database schema

1. Open cPanel → **phpMyAdmin**.
2. Select the database you created in the left sidebar.
3. Open the **Import** tab.
4. Select `database.sql` from the clean package.
5. Keep the format as SQL and click **Import**.
6. Confirm that tables such as `users`, `orders`, `transactions`, `notifications`, and `services_cache` appear.

The schema is designed to be imported into the database selected in phpMyAdmin. Do not import it into a different database.

## 4. Create the application `.env` file

In the application document root, create a new file named exactly `.env` and add:

```env
APP_NAME=Royal
APP_URL=https://your-domain.com

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpaneluser_royal_smm
DB_USER=cpaneluser_royal_user
DB_PASS=your-database-password

PRIMARY_PROVIDER=fastway
FASTWAY_API_KEY=your-fastway-api-key
FASTWAY_API_BASE_URL=https://fastwaysmm.com/api/v2
FASTWAY_API_TIMEOUT=30
FASTWAY_API_VERIFY_SSL=true

USD_TO_TZS_RATE=3500
PRICE_MARKUP_PERCENT=78

BREVO_API_KEY=your-brevo-api-key
BREVO_API_URL=https://api.brevo.com/v3/smtp/email
BREVO_FROM_NAME=Royal
BREVO_FROM_EMAIL=your-verified-sender@example.com
BREVO_REPLY_TO=your-verified-sender@example.com
BREVO_API_TIMEOUT=8
```

The application reads `.env` automatically. Do not put values in public HTML or JavaScript files.

## 5. Configure PHP in cPanel

1. Open **Select PHP Version** or **MultiPHP Manager**.
2. Select PHP **8.2** or newer for the domain.
3. Enable these extensions if the interface provides them:
   - `curl`
   - `pdo`
   - `pdo_mysql`
   - `mbstring`
   - `openssl`
   - `json`
4. Set the PHP timezone to `Africa/Dar_es_Salaam` if available.
5. Save the changes.

The app uses MySQL on Namecheap. It does not require SQLite or PostgreSQL for this deployment.

## 6. Configure HTTPS

1. In cPanel, open **SSL/TLS Status**.
2. Run AutoSSL if the certificate is not already active.
3. Visit `https://your-domain.com` and confirm the padlock appears.
4. Make sure `APP_URL` in `.env` uses `https://`, not `http://`.

Never use the admin panel or payment forms over plain HTTP.

## 7. Configure Brevo email

1. In Brevo, verify the sender address under **Transactional → Senders**.
2. Create an API key under **SMTP & API → API keys**.
3. Put the API key in `BREVO_API_KEY`.
4. Set `BREVO_FROM_EMAIL` to the exact verified sender address.
5. Register a test account on the site.
6. Check Brevo → **Transactional → Logs** and the recipient inbox/spam folder.

The application sends email using Brevo's HTTPS API on port 443, not a direct SMTP socket.

## 8. First login and security

The imported schema includes a default admin account from `database.sql`. Log in once, then immediately:

1. Change the administrator password.
2. Remove or disable any unused admin account.
3. Confirm the admin email address.
4. Do not share the `.env` file.
5. Do not leave database backups in `public_html`.
6. Rotate any API key that was ever exposed in an old ZIP or public repository.

## 9. Test the installation

Run these checks in order:

1. Open the home page.
2. Register a test user.
3. Log in and confirm the balance displays in TSh.
4. Open **Services** and confirm platform/category tabs load.
5. Search for a service by name and Service ID.
6. Open the admin dashboard.
7. Confirm the provider balance card works.
8. Create a small test order only after confirming the service ID and price.
9. Open Orders and use the explicit provider refresh control.
10. Test the chatbot in English and Kiswahili.
11. Test a registration email and an order email.

If a page shows a blank response, check cPanel → **Errors** and the PHP error log. Do not enable `display_errors` in production.

## 10. Common Namecheap problems

### Database connection failed

Check the cPanel-prefixed database name/user, `DB_HOST=localhost`, password, and that the user has ALL PRIVILEGES.

### Services do not load

Check that `FASTWAY_API_KEY` is correct, cURL is enabled, and outbound HTTPS is available. Check the PHP error log.

### Email is not sent

Confirm that the Brevo API key is an API key, not an SMTP key, and that `BREVO_FROM_EMAIL` is verified exactly. Check Brevo Transactional Logs.

### `.env` gives a 403 error

That is expected and desirable. The included `.htaccess` blocks direct downloads of `.env` and SQL files. PHP can still read the file server-side.

### File permissions

Use normal cPanel permissions: directories `755`, PHP files `644`. The `data/` directory must be writable if the provider cache needs to be created; start with `755`, then use `775` only if the host requires it.

## Clean deployment checklist

- [ ] Old uploaded configuration was not copied
- [ ] `.env` created with Namecheap database values
- [ ] `database.sql` imported into the selected database
- [ ] PHP 8.2+ selected
- [ ] `pdo_mysql` and cURL enabled
- [ ] FastWay API key configured
- [ ] Brevo API key and verified sender configured
- [ ] HTTPS certificate active
- [ ] Admin password changed
- [ ] Test registration, service search, order, status, and email completed
