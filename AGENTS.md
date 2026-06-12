# MAX-APP Working Rules

## Working Mode

* Read only files related to the current task.
* If correct implementation requires understanding module connections, related files may be read.
* Search the project when necessary to find related code, services, routes, models, API endpoints, translations, permissions, or shared logic.
* Do not run git commit or git push. The developer does this manually.
* Do not run heavy whole-project checks unless necessary.
* After changes, check only modified PHP/JS/Python files.
* Keep reports concise.

## Reporting

Write briefly:

* what changed;
* which files changed;
* what should be checked after deployment;
* any risks or follow-up recommendations.

Do not output large code listings unless explicitly requested.

## Scope

* Do not refactor outside the current task.
* Do not rewrite working modules without a clear reason.
* Do not perform large architectural redesigns on your own initiative.
* If an architectural issue is found, report it separately instead of fixing it automatically.
* Prefer minimal, safe, and focused changes.
* For large tasks, work in small logical steps instead of modifying many modules at once.

## Database

* The database schema may evolve during development.
* Tables, fields, indexes, and relations may be added or adjusted when required by the current task.
* New migrations should be additive whenever possible.
* Keep backward compatibility whenever reasonably possible.
* Report schema changes in the final summary.
* Do not perform destructive database operations without explicit approval.

## Code Style

### PHP

* Use PHP 8+ features where appropriate.
* Use PDO prepared statements for database access.
* Do not introduce Laravel, Symfony, ORM frameworks, CMS systems, or unnecessary dependencies.
* Do not reformat unrelated code.

### Python

* Keep bot logic modular.
* Reuse existing services whenever possible.
* Avoid duplicating business logic.
* Prefer shared services or API endpoints for common functionality.

### JavaScript

* Keep code simple and maintainable.
* Avoid introducing large frontend frameworks unless requested.

## Exceptions

If the task affects:

* localization;
* permissions;
* recommendations;
* authorization;
* referral logic;
* shared services;
* shared API endpoints;
* database relations;

then all related files may be analyzed as needed for a correct implementation.

## Safety

* Do not expose secrets, tokens, passwords, or API keys.
* Keep configuration values in .env, local config files, or server environment variables.
* Do not disable security checks without explicit approval.

## General Principle

Optimize for correctness, maintainability, and development speed.

Saving tokens must never reduce implementation quality or lead to incorrect architectural decisions.
