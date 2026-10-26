# NexSphere Project Management

NexSphere is a project and task management application with a Laravel web backend, Vue 3/Inertia web interface, and a Flutter mobile client. The backend also exposes a Sanctum-protected REST API under `/api/v1`.

## Current stack

- PHP `^8.3` and Laravel `^13.17` (see `composer.json`).
- Vue 3, Inertia, and Vite for the web application.
- Flutter for the mobile client; API requests use Dio and Sanctum bearer tokens.
- Database queue support is configured, but individual work may still run synchronously. Check the code before assuming a job is queued.

## Local setup

1. Install the PHP/Composer and Node.js versions required by the project.
2. Install PHP dependencies:

   ```sh
   composer install
   ```

3. Create the local environment file and application key if needed:

   ```sh
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure a local database in `.env`, then run migrations. Use `--seed` only when you want the development sample data:

   ```sh
   php artisan migrate
   ```

5. Install frontend dependencies and build assets:

   ```sh
   npm install
   npm run build
   ```

6. Start the Laravel development server:

   ```sh
   php artisan serve
   ```

For frontend development with Vite, run `npm run dev` in another terminal.

## Mobile client

See [mobile/README.md](mobile/README.md) for Flutter setup and API base URL configuration. Do not put production secrets or personal access tokens in source control.

## Tests

Run the current test suite with:

```sh
php artisan test
```

Test results depend on the current checkout and environment; historical counts in project notes are not a substitute for running the tests.

## Project documentation

See the [documentation index](docs/README.md) for the folder structure and guidance on interpreting the project documents.

- [Product requirements](docs/product/PRD.md): product scope and requirements; currently a draft for validation.
- [Project plan](docs/planning/PROJECT_PLAN.md): technical direction and staged roadmap.
- [ERD](docs/architecture/ERD.md): intended domain model and data relationships.
- [Shared hosting guide](docs/operations/SHARED_HOSTING_GUIDE.md): deployment checklist; verify provider capabilities and current application configuration before using it.
- [Development log](docs/history/DEVELOPMENT_LOG.md): historical progress notes, not a live implementation or test-status report.
