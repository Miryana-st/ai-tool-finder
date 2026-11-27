## Project Context
AI-Tool-Finder is a role-based SaaS platform for discovering and managing AI tools.

The application has separate Laravel backend and Next.js frontend.
Development is done incrementally, starting with the backend.

## Technical Decisions
- Laravel handles backend logic, authentication, authorization, and database operations.
- Next.js is a separate frontend application.
- MySQL is the primary database.
- Sanctum will handle SPA authentication using secure cookies.
- Docker is used for the development environment.
- Do not use Prisma; database operations belong to Laravel.

## Development Workflow
- Inspect the existing code and project structure before making changes.
- Read relevant controllers, models, services, migrations, and tests.
- Understand the current behavior before modifying it.
- Keep changes focused on the requested task.
- Do not introduce unrelated changes or refactoring.
- Preserve existing functionality unless a change is explicitly required.

## Git Workflow
- Never make changes directly on `main`.
- Use a separate branch for each feature, fix, or refactoring task.
- Use descriptive branch names, e.g. `feat/user-roles`, `fix/login-validation`.
- Check `git status` before making changes.
- Never overwrite or discard unrelated user changes.
- Review the diff before committing.
- Ask for approval before creating commits.
- Never push directly to `main` or use `git push --force` without permission.
- Do not commit or push without explicit authorization.

## Coding Conventions
- Follow Laravel conventions and PSR standards.
- Use meaningful names for classes, methods, and variables.
- Prefer readable code over clever or unnecessarily complex solutions.
- Use dependency injection.
- Keep controllers focused on HTTP responsibilities.
- Put business logic in focused Services or Actions when appropriate.
- Avoid unnecessary interfaces, repositories, and abstractions.
- Use asynchronous operations only where supported and appropriate by the framework.

## Database
- Use existing Eloquent models, relationships, and migrations.
- Use transactions when multiple database operations must succeed together.
- Use `DB::transaction()` where appropriate.
- Avoid unnecessary queries and loading unused relationships.
- Consider existing data before changing database schemas.

## Validation & Security
- Validate incoming data using Form Requests where appropriate.
- Enforce authorization on the backend.
- Never expose sensitive information, credentials, or internal errors.
- Handle expected errors explicitly.
- Never silently swallow exceptions.

## Testing & Verification
- Add or update tests when changing business logic.
- Test successful scenarios, validation failures, and edge cases.
- Use the existing testing conventions.
- Run relevant tests after changes:
  `docker compose exec app php artisan test`
- Report actual test results. Never claim tests passed if they were not executed.

## Dependencies & Scope
- Prefer existing Laravel and PHP capabilities.
- Add dependencies only when clearly necessary.
- Do not introduce new architectural patterns without a reason.
- Explain larger changes before expanding the task scope.

## Definition of Done
- Requested functionality is implemented.
- Relevant tests pass, or failures are reported.
- No unrelated changes remain.
- Final changes have been reviewed.
- Documentation is updated when necessary.
