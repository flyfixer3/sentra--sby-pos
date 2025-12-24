# Decisions Log

Short, append-only record of technical decisions for this project. Newest on top.

## 2025-07-21 — Transfer print & confirmation flow
- Printing a transfer sets `printed_at` and `printed_by`, and updates status to `shipped`.
- Reprints are restricted to roles: `Super Admin`, `Administrator`, `Supervisor`.
- Every print action is logged into `print_logs` with `user_id`, `transfer_request_id`, `printed_at`, `ip_address`.
- Confirmation at destination branch requires `to_branch_id == session('active_branch')`, sets `to_warehouse_id` and `delivery_proof_path`, and writes IN stock mutations.

## 2025-07-21 — Branch scoping & BaseModel
- All branch-aware models must have a `branch_id` column and extend `App\Models\BaseModel`.
- `HasBranchScope` applies a global scope filtering by `session('active_branch')` when authenticated.
- `BaseModel` sets `created_by`/`updated_by` on create/update and logs deletes via Spatie Activitylog.

## 2025-07-21 — Packages and patterns
- Use Laravel Sanctum for API auth; Laravel UI for web auth.
- Use Yajra DataTables for server-side listing.
- Use Barryvdh DomPDF for printable documents (with Snappy available if needed).
- Use Spatie Permission for authorization and Spatie Activitylog for auditing.

## 2025-07-21 — Routing & modules
- Use `nwidart/laravel-modules` structure for domain modules (Branch, Product, Transfer, etc.).
- Keep module routes under `Modules/<Module>/Routes/` with appropriate middleware and prefixes.

