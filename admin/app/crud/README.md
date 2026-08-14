# CRUD architecture

`admin/public/crud.php` is intentionally a small, stable HTTP entry point. Existing
URLs and form payloads remain compatible with `crud.php?module=...`.

The implementation is split by responsibility:

- `modules.php` — declarative module, column and field registry;
- `controller.php` — request/action orchestration;
- `scopes.php` — role and ownership scopes;
- `fields.php`, `uploads.php`, `validation.php` — generic form pipeline;
- `user_merge.php`, `user_promotion.php`, `user_accounts.php` — client identity workflows;
- `limits.php` — plan and team limit enforcement;
- `staff_access.php` — linked admin-account access;
- `deletion.php` — record-specific deletion cleanup;
- `operations.php` — persistence and assignment synchronization;
- `integrations.php` — integration-specific helpers.

HTML lives in `admin/app/views/crud/`, while browser behavior lives in
`admin/public/assets/js/crud.js`. Business logic must not be added to view files.
