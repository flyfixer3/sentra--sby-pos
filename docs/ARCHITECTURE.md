# Architecture Overview

## Stack & Packages
- Laravel 8 (`laravel/framework` ^8.40)
- Modules via `nwidart/laravel-modules`
- Auth: Laravel UI (web), Sanctum (API)
- UI/UX: Livewire 2, SweetAlert
- Data: Yajra DataTables
- Media & Audit: Spatie Permission, Activitylog, Medialibrary
- PDF: Barryvdh DomPDF, Snappy (wkhtmltopdf)

## High-Level Structure
- `app/` core (Kernel, Middleware, BaseModel, Models, Controllers)
- `Modules/*` domain modules (e.g., Branch, Product, Transfer, Sale, Purchase, Mutation)
- `routes/web.php` base web routes; `routes/api.php` API endpoints (Sanctum)
- `config/*` project-wide config (CORS, activitylog, etc.)

## Branching Model
- Active branch stored in `session('active_branch')`.
- `SetActiveBranch` middleware (web group) sets it for logged-in users based on role:
  - Super Admin: first branch by default
  - Other roles: first of their assigned branches via pivot `branch_user`
- `HasBranchScope` trait adds a global scope filtering `branch_id` where column exists.
- All domain models should extend `App\Models\BaseModel` to inherit audit and branch behaviors.

## Key Components
- `app/Http/Kernel.php`: Registers `SetActiveBranch` inside `web` middleware.
- `app/Traits/HasBranchScope.php`: Applies branch scope when `auth()` and session branch exist.
- `app/Models/BaseModel.php`: Sets `created_by`/`updated_by`, logs deletes, configures activity logging.
- `app/Models/User.php`: Uses Spatie Roles; relations to branches; `allAvailableBranches()` helper.

## Modules
### Branch
- Routes: `Modules/Branch/Routes/web.php`
- Switch active branch: `POST /switch-branch` (validates access)
### Product
- Entities: `Warehouse` (has `branch_id`)
- Routes: previews, products, barcode, categories, warehouses
### Mutation
- Records stock movements (IN/OUT) with running balances (`stock_early`, `stock_in`, `stock_out`, `stock_last`).
### Transfer
- Purpose: Inter-branch stock transfer.
- Routes: `Modules/Transfer/Routes/web.php` — index (outgoing/incoming), datatables, create/store, show, confirm, print, destroy.
- Controller (`TransferController`):
  - `store`: create header/items; write OUT mutations from sender warehouse.
  - `printPdf`: set `printed_at/by`, status `shipped`, restrict reprints to privileged roles, log print to `print_logs`, render DomPDF.
  - `showConfirmationForm`: only receiver branch; list warehouses in active branch.
  - `storeConfirmation`: save delivery proof, set `to_warehouse_id`, write IN mutations, mark `confirmed`.
- Entities:
  - `TransferRequest`: header with from/to, status, audit fields, printed/confirmed fields.
  - `TransferRequestItem`: product lines with quantities.
  - `PrintLog`: audit trail of document printing.

## Database Outline (Transfer)
- `transfer_requests`:
  - `reference` (unique), `date`, `from_warehouse_id`, `to_branch_id`, `to_warehouse_id?`, `note`
  - `status` (pending/shipped/confirmed/...)
  - `printed_at/by?`, `delivery_proof_path?`, `confirmed_by/at?`
  - `branch_id`, `created_by`, timestamps
- `transfer_request_items`: `transfer_request_id`, `product_id`, `quantity`, timestamps
- `print_logs`: `user_id?`, `transfer_request_id`, `printed_at`, `ip_address?`, timestamps

## API & CORS
- `routes/api.php`: `POST /api/masuk` (token login), accounting endpoints under `auth:sanctum`.
- `config/cors.php`: permissive origins + credentials enabled for local and specified hosts.

## Patterns & Guidance
- Use transactions for multi-step writes (create/confirm transfer, posting stock mutations).
- Always consider branch scope when querying; most model queries are auto-filtered.
- Guard page-level permissions with Spatie roles/permissions and Gate checks.
- For listing pages, expose JSON endpoints for DataTables and render blade views.
- For printable flows, persist and audit print events and restrict reprints appropriately.

## Notes
- Verify `Branch::warehouses()` points to the intended model (currently `ProductWarehouse`).
- Keep controller naming consistent (`WarehousesController` vs `WarehouseController`).

