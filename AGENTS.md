# Repository Instructions

This repository is the NexSphere Project Management application. Check the current code and configuration before relying on documentation claims.

## Before changing application code

- Verify `php -v` and `composer -V`. The project requires PHP `^8.3` and Laravel `^13.17` (see `composer.json`). Do not install or upgrade runtimes automatically; report missing prerequisites.
- Read the relevant application code and tests, and check `git status` before editing. Preserve unrelated user changes.
- Laravel Boost is already declared in `require-dev`. Do not run `composer require laravel/boost` unless the dependency is actually missing and a dependency change is needed. Do not run `php artisan boost:install` automatically; first inspect whether Boost setup is needed and whether it would replace repository guidance.
- Do not run production migrations, modify production environment files, or deploy without explicit authorization.

## Project-specific guidance

- Web: Laravel routes/controllers, Vue 3, and Inertia. Mobile: Flutter using the versioned Sanctum API under `/api/v1`.
- Keep authorization and tenant scoping on the server. Hiding a control in the UI is not authorization.
- Check role, workspace/space ownership, and related model scope for every read/write endpoint. Keep web and API rules consistent.
- Treat uploads as private project data. Verify storage visibility and access checks before changing upload or preview behavior.
- The web Kanban currently uses periodic HTTP polling. Do not describe it as WebSocket push or true realtime unless that has been implemented and verified.
- Email completion notifications currently use synchronous mail sending. Queue behavior and deployment support must be verified before changing it.
- `docs/product/PRD.md`, `docs/planning/PROJECT_PLAN.md`, and `docs/architecture/ERD.md` describe product intent and may contain draft requirements. `docs/history/DEVELOPMENT_LOG.md` contains historical claims. Verify the implementation before treating any document as evidence of current behavior.
- Do not add dependencies, infrastructure, migrations, or unrelated documentation changes unless needed for the requested task.
