# Agent Guide for sentra--sby-pos

Scope: This file sits at the repo root and applies to the entire project tree.

## Project Snapshot
- Stack: Laravel 8, PHP ^7.3|^8.0, Livewire 2, Sanctum, Spatie (Permission, Activitylog, Medialibrary), Yajra DataTables, DomPDF/Snappy.
- Structure: Modular via `nwidart/laravel-modules` (Modules/*) + classic Laravel app (app/*, routes/*, config/*).
- Auth: Web via Laravel UI; API via Sanctum tokens.
- Multi-branch: Every model that has `branch_id` is globally scoped to `session('active_branch')`.

## Read-First Files
- routes/web.php — base web routes (dashboard, charts)
- routes/api.php — API routes (Sanctum group, accounting endpoints)
- app/Http/Kernel.php — includes `SetActiveBranch` in `web` group
- app/Http/Middleware/SetActiveBranch.php — auto sets `session('active_branch')` for authenticated users
- app/Traits/HasBranchScope.php — adds global branch scope to models with `branch_id`
- app/Models/BaseModel.php — base behaviors: activity logs, created_by/updated_by, delete logs
- Modules/**/Routes/*.php — module web/api routes
- Modules/**/Entities/*.php — module Eloquent models

## Core Conventions
- New models should extend `App\Models\BaseModel` and (if applicable) include a `branch_id` column.
- Queries should naturally filter by `branch_id` via `HasBranchScope`. Avoid bypassing unless explicitly needed.
- When adding migrations for new domain tables, include `branch_id` where relevant and index foreign keys.
- Use Spatie Permission for authorization; guard actions with `Gate::denies()` or policies where present.
- Use Yajra DataTables for listing pages; keep server-side endpoints under module route groups.
- For printable docs (e.g., Transfer), follow pattern: set `printed_at`/`printed_by`, log to `print_logs`, and restrict reprints to roles: Super Admin, Administrator, Supervisor.
- For stock flow, record movements in `Modules\Mutation\Entities\Mutation` (OUT on ship, IN on confirm) with last/early balances.

## Branch Logic
- `SetActiveBranch` (web middleware) ensures `session('active_branch')` is set for logged-in users.
- `User::allAvailableBranches()` returns either all branches (Super Admin) or pivot-linked branches.
- Switch active branch via `POST /switch-branch` with access validation.

## Typical Flow: Transfer Module
- Create (sender branch): create header + items; write OUT mutations from `from_warehouse_id`.
- Print (sender): set `printed_at/by`, set status `shipped`, log to `print_logs`; reprint restricted by role.
- Confirm (receiver branch): ensure `to_branch_id == active_branch`, set `to_warehouse_id`, upload delivery proof, write IN mutations, set status `confirmed`.

## Coding Style & Practices
- Keep changes minimal and aligned with existing patterns (controllers in Modules/**/Http/Controllers, resources in Modules/**/Resources).
- Use meaningful variable names; avoid one-letter names.
- Add validations in controllers or form requests; prefer transactions for multi-write flows.
- Prefer dependency imports at the top; avoid fully-qualified calls inline unless necessary.

## Safety & Gotchas
- Many tables include `branch_id`; the global scope will filter automatically. Be careful with cross-branch operations.
- Ensure new queries that need cross-branch access intentionally remove or bypass the global scope.
- Reprint restrictions: enforce via roles as implemented in Transfer printing.
- Note: `Modules/Branch/Entities/Branch::warehouses()` currently points to `ProductWarehouse`. Verify the intended relation before relying on it.
- Note: `Modules/Product/Routes/api.php` references `WarehouseController` but web uses `WarehousesController`. Keep controller names consistent when modifying.

## Quick Agent Checklist (on load)
1) Skim routes (routes/*.php, Modules/**/Routes/*.php)
2) Skim Kernel + middleware + BaseModel + HasBranchScope
3) Skim relevant module Entities/Controllers for the task
4) Confirm migrations and columns for `branch_id`, audit fields
5) Respect roles/permissions; use Sanctum for API endpoints

## Testing/Validation
- Prefer focused tests on the changed areas. Use transactions and factories where present.
- For DataTables endpoints, validate JSON shape and query scoping.

## Communication
- Keep diffs targeted; document any assumptions or cross-module impacts in PR descriptions or docs.

