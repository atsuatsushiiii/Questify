# Deployment guide

Questify is a plain PHP 8.2+ / MySQL application, so it runs on shared hosting,
a VPS, or a PaaS. One rule matters more than any other:

> **The web server document root must point at `public/`, not the project root.**

Everything below assumes that. If the document root is the project root, routing,
assets, and the dotfile protections all break.

## Before you start

You need:

- A domain name.
- A host with **PHP 8.2+**, **MySQL 8+ / MariaDB 10.4+**, and **HTTPS**.
- Writable `storage/` and `public/uploads/` directories.
- A built asset bundle (`public/assets/css/app.css`, `public/assets/js/app.js`).
  These files are gitignored and must be built or uploaded.

Build them locally before packaging:

```powershell
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

## 1. Production environment file

Copy [`.env.production.example`](../.env.production.example) to `.env` on the
host and fill in real values:

```ini
APP_NAME=Questify
APP_ENV=production
APP_DEBUG=false
APP_URL=https://questify.example.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=questify
DB_USERNAME=questify_app
DB_PASSWORD=use-a-long-random-password

SESSION_COOKIE_NAME=questify_session
SESSION_SECURE=true
REMEMBER_COOKIE_NAME=questify_remember

MAIL_TRANSPORT=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=questify@example.com
MAIL_PASSWORD=use-the-smtp-password
MAIL_ENCRYPTION=tls
MAIL_TIMEOUT=10
MAIL_FROM_ADDRESS=no-reply@questify.example.com
MAIL_FROM_NAME=Questify

GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
# GOOGLE_REDIRECT_URI=https://questify.example.com/auth/google/callback
```

`APP_URL` must be the exact public origin, with no trailing slash.
`SESSION_SECURE=true` requires HTTPS, so enable the certificate before testing
sign-in.

## 2. Upload the code

### With SSH or cPanel Terminal

```bash
cd ~
git clone https://github.com/atsuatsushiiii/Questify.git questify
cd questify
composer install --no-dev --optimize-autoloader
npm ci && npm run build   # skip if Node is unavailable
```

If the host has no Node, build the assets on your machine and upload
`public/assets/` after cloning.

### Without SSH (File Manager)

Build locally, then create a zip that includes the runtime files:

```powershell
git archive --format=zip -o questify.zip HEAD
Compress-Archive -Path vendor,public/assets/css,public/assets/js -Update -DestinationPath questify.zip
```

Upload `questify.zip` through cPanel File Manager and extract it to
`/home/USER/questify`. Do **not** upload `.env`; create it on the host instead.

> `git archive` only includes committed files. Commit your work first, or use a
> normal folder zip that excludes `.git`, `node_modules`, `tests`, `site`,
> `.vscode`, `storage/logs/*`, and `.env`.

## 3. Point the domain at `public/`

### cPanel with a configurable document root

**Domains → Manage → Document Root** and set:

```
/home/USER/questify/public
```

This is the cleanest option. Enable AutoSSL for the domain.

### cPanel where `public_html` is fixed

Use a symlink from the Terminal:

```bash
mv ~/public_html ~/public_html.bak
ln -s ~/questify/public ~/public_html
```

If symlinks are disabled, copy the contents of `public/` into `public_html/`,
keep the rest of the app at `~/questify/`, and change line 7 of
`public_html/index.php` to:

```php
define('BASE_PATH', '/home/USER/questify');
```

That works, but every app update must preserve the edited line.

## 4. Database

Create a database and a least-privilege user in cPanel or MySQL:

```sql
CREATE DATABASE questify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'questify_app'@'localhost' IDENTIFIED BY 'use-a-long-random-password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON questify.* TO 'questify_app'@'localhost';
FLUSH PRIVILEGES;
```

Run the migrations:

```bash
php scripts/migrate.php migrate
php scripts/migrate.php status
```

### No Terminal available

Import a dump from a database that is already migrated. On your machine:

```powershell
mysqldump --single-transaction --routines --triggers -u root questify > questify.sql
```

Import `questify.sql` through phpMyAdmin. The dump includes the `migrations`
table, so the host knows which migrations are already applied.

### Create the first administrator

Register normally, then promote the account:

```sql
UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
```

Do not run `scripts/seed_demo.php` against production data.

## 5. Permissions

The web server user needs write access to the runtime directories:

```bash
chmod -R 775 storage public/uploads
```

On cPanel the account user usually owns the files, so `755` directories and
`644` files are enough. Avoid `777`.

## 6. HTTPS and Google OAuth

1. Enable AutoSSL / Let's Encrypt for the domain.
2. Confirm `APP_URL` starts with `https://` and `SESSION_SECURE=true`.
3. In Google Cloud Console → **Credentials → your OAuth client**, add:

   ```
   https://questify.example.com/auth/google/callback
   ```

4. Keep the localhost URI too if you still develop locally.
5. If the consent screen is in **Testing**, add every Google account that needs
   to sign in under **Audience → Test users**, or publish the app.

## 7. Preflight and smoke test

Run the readiness check on the host:

```bash
php scripts/preflight.php
```

It verifies the PHP version, extensions, built assets, writable directories,
production environment values, Google redirect URI, database connection, pending
migrations, and SMTP settings. It exits non-zero on blocking problems.

Then check:

- [ ] `https://questify.example.com/` loads over HTTPS.
- [ ] `https://questify.example.com/api/health` reports `"database":"ok"`.
- [ ] `/register`, `/login`, and `/logout` work.
- [ ] Google sign-in completes and returns to `/dashboard`.
- [ ] A password-reset email arrives (check spam and SMTP logs).
- [ ] Creating and completing a quest awards XP.
- [ ] Avatar upload writes to `public/uploads/avatars`.
- [ ] The admin account can open `/admin`.
- [ ] Response headers include CSP, `X-Frame-Options: DENY`, and `nosniff`.

## 8. Updates

```bash
cd ~/questify
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php scripts/migrate.php migrate
php scripts/preflight.php
```

Restart PHP-FPM on a VPS after an update if you use opcache with
`opcache.validate_timestamps=0`.

## 9. Backups

```bash
mysqldump --single-transaction --routines --triggers questify > questify-$(date +%F).sql
tar -czf uploads-$(date +%F).tar.gz public/uploads/avatars
```

Keep backups off the host and test a restore into an empty database
periodically. `storage/logs` is disposable; the database and avatars are not.

## Option B: VPS (Ubuntu 24.04 + nginx)

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-mbstring \
    php8.3-curl php8.3-xml php8.3-zip composer git unzip

sudo mkdir -p /var/www/questify
sudo chown -R "$USER":www-data /var/www
git clone https://github.com/atsuatsushiiii/Questify.git /var/www/questify
cd /var/www/questify
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.production.example .env
# edit .env, then:
php scripts/migrate.php migrate

sudo chown -R www-data:www-data storage public/uploads
sudo find storage public/uploads -type d -exec chmod 775 {} \;
sudo find storage public/uploads -type f -exec chmod 664 {} \;
```

Create `/etc/nginx/sites-available/questify`:

```nginx
server {
    listen 80;
    server_name questify.example.com;
    root /var/www/questify/public;
    index index.php;
    client_max_body_size 5m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable it and issue the certificate:

```bash
sudo ln -s /etc/nginx/sites-available/questify /etc/nginx/sites-enabled/questify
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d questify.example.com
```

`certbot` rewrites the server block for HTTPS. Confirm
`https://questify.example.com/api/health` before configuring Google.

## Option C: PaaS (Railway, Render, Fly.io)

PaaS works for a demo, but budget for a persistent volume and a managed MySQL
instance. A serverless filesystem loses avatar uploads between deploys.

- Build: `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
- Start: `php -S 0.0.0.0:$PORT -t public scripts/router.php`
  (fine for light traffic; use a PHP/Apache or nginx buildpack for production)
- Set every variable from `.env.production.example` in the dashboard.
- Attach a persistent volume to `storage/` and `public/uploads/avatars`.
- Run migrations as a one-off shell command: `php scripts/migrate.php migrate`.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Every route returns 404 | Document root is not `public/`, or `mod_rewrite`/`AllowOverride` is off |
| CSS and JS 404 | Assets were not built or `public/assets/` was not uploaded |
| 500 on every page | `vendor/` missing, `.env` unreadable, or `storage/` not writable |
| Login redirects in a loop | `SESSION_SECURE=true` without HTTPS, or the session table is unreachable |
| `redirect_uri_mismatch` from Google | Registered URI differs from `APP_URL/auth/google/callback` |
| Google says “Access blocked” | Consent screen is in Testing and the account is not a test user |
| Password-reset mail never arrives | SMTP host/port/encryption wrong, or SPF/DKIM not configured |
| Uploads fail | `public/uploads/avatars` not writable by the web server user |

For deeper production notes, see [`SECURITY.md`](SECURITY.md).
