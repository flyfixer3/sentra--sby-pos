# TODO / Backlog

Keep it short and actionable. Check off as changes merge.

- Verify relation: `Modules/Branch/Entities/Branch::warehouses()` points to `ProductWarehouse`; confirm intended model or change to `Warehouse`.
- Fix controller naming consistency in Product routes: `Modules/Product/Routes/api.php` references `WarehouseController` vs web uses `WarehousesController`.
- Align transfer status values: initial migration uses `['pending','confirmed','rejected']`, later adds `['pending','shipped','confirmed','completed']`. Decide final set and migrate data accordingly.
- Add proper `down()` for `2025_07_19_170843_add_status_print_fields_to_transfer_requests.php` (currently `Schema::table('')`).
- Add foreign keys/indexes for `transfer_requests`: `from_warehouse_id`, `to_branch_id`, `branch_id`, `to_warehouse_id` (where appropriate).
- Ensure permissions exist for `access_transfers`, `create_transfers`, `confirm_transfers` and are seeded (check `Modules/User/Database/Seeders/PermissionsTableSeeder.php`).
- Audit models for `branch_id` presence and extension from `BaseModel`; add where missing.
- Add feature tests for Transfer: create → print (role restrictions) → confirm; verify mutations and logs.
- Review CORS allowed origins for production hardening.
- Document environment prerequisites for PDF (wkhtmltopdf if using Snappy) and storage symlink for delivery proofs.

