# Shared Hosting Deployment Checklist

## NexSphere Project Management

This is a conservative deployment checklist, not a provider-specific guarantee. Confirm PHP extensions, CLI version, database, cron, storage permissions, outbound mail, queue support, and resource limits with the hosting provider before deployment. Do not copy example credentials into a real `.env` file.

The repository targets PHP `^8.3` and Laravel `^13.17`. The application is currently configured with database sessions/cache/queue in `.env.example`, `BROADCAST_CONNECTION=log`, and `FILESYSTEM_DISK=local`. Hosting configuration must be chosen based on services actually available.

## 1. Keep application files outside the public web root

Keep `.env`, `app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`, `storage/`, and `vendor/` outside `public_html`. Configure the domain document root to Laravel's `public/` directory where the provider allows it. If it does not, use the provider's documented Laravel setup to expose only the contents of `public/`; do not expose the application root.

Build frontend assets before deployment and include the generated `public/build` files. Do not copy the entire `storage` directory into a public web root.

## 2. Verify the runtime and install dependencies

Check the actual PHP CLI binary and required extensions on the hosting account. The PHP version used by SSH/Composer must satisfy the project requirement, not just the version selected for the web server.

Use the committed lock files for reproducible installation:

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

If Node.js is unavailable on the host, build assets in a compatible CI/local environment and deploy the generated `public/build` directory. Do not run Composer update as part of deployment.

## 3. Production environment and release

Create `.env` on the server and configure production values privately. At minimum, verify:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
```

Set a unique `APP_KEY`, production database credentials, mail settings, session/cache/queue drivers supported by the host, and restrictive file permissions. Never commit `.env` or paste real secrets into documentation.

Back up the database and files before applying migrations. Run `php artisan migrate --force` only against the intended production database as part of an approved release. Then apply Laravel's supported config/route/view caching where compatible with the deployed routes and environment. Verify login, authorization, API, uploads, logs, and rollback steps on staging first.

## 4. Uploaded files — private storage & security architecture

Task attachments are stored privately on Laravel's `local` disk (`storage/app/tasks/`) and are never exposed directly to the public web root.
- **Access control**: Preview and download endpoints verify user authentication and workspace/space membership on every request (`TaskAttachmentController`). Unauthorized requests receive `403 Forbidden`.
- **Validation**: Strict extension allowlist (`jpg, jpeg, png, webp, pdf, docx, xlsx, txt, zip`) and explicit blocking of executable/script extensions (`php, phtml, exe, sh, svg, html, js`, etc.). Maximum file size is enforced (20MB).
- **Storage obfuscation**: Files are saved with random UUIDs (`Str::uuid()`), while original sanitized filenames are preserved in the database.
- **Security headers**: Streaming responses send `X-Content-Type-Options: nosniff` and proper `Content-Type`. Do not run `php artisan storage:link` to expose attachment directories.

## 5. Queue, mail, scheduler, and realtime WebSocket status

- **Realtime WebSocket (Laravel Reverb & Echo)**:
  - Collaborative sync uses WebSocket push via Laravel Reverb (`broadcaster: reverb` / `pusher-js`).
  - Automatic HTTP polling (`setInterval`) has been completely removed.
  - Domain events implement `ShouldBroadcastNow` and dispatch immediately after database commit (`DB::afterCommit`) to private channels (`private-space.{spaceId}`).
  - **Shared hosting requirement**: Reverb requires a persistent daemon process (`php artisan reverb:start`). If your shared hosting environment does not allow long-running daemon background processes, run Reverb on a separate VPS or Node/WS service, or configure a daemon process via Supervisor / cPanel Terminal / Node.js reverse proxy.
  - Set `BROADCAST_CONNECTION=reverb` along with the matching `REVERB_*` and `VITE_REVERB_*` credentials in `.env`.
- **Email & Queue**:
  - Task completion email notifications send safely with fallback logging if SMTP is unreachable.
  - Database queue requires a worker (`php artisan queue:work` or cron worker). Confirm hosting limits before backgrounding.
- **Scheduler**:
  - Review `routes/console.php` for scheduled tasks. Inspect tenant health and subscription maintenance commands before activating cron.

## 6. Cron and operational checks

If the application has scheduled commands that are needed in production, configure the hosting cron to run Laravel's scheduler once per minute using the verified PHP CLI binary and absolute application path:

```sh
* * * * * /path/to/php /path/to/application/artisan schedule:run
```

This is a template, not a ready-to-run provider command. Confirm the path, cron syntax, output logging, and scheduled tasks on the actual account. Monitor application logs, failed jobs, disk use, database backups, and restore capability.

## 7. Before moving to a VPS

Treat a hosting migration as a deployment project, not an `.env` change. Plan runtime versions, database and file transfer, secrets, TLS, queue workers, scheduler, storage, backups, monitoring, DNS cutover, and rollback. Enable Laravel Reverb only after its process manager and reverse proxy have been deployed and tested.
