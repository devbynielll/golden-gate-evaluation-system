# Phase 3: office staff and separate staff criteria

Development baseline: `08e12b83109f0bcf68866254f2acd8bb7c0f0b9f`, continuing Phases 1A, 1B and 2 in `feature/ggc-post-defense-revisions`. This is source implementation, not deployment approval.

## Data model and behavior

The additive migration `2026_11_19_000001_create_office_staff_and_staff_criteria_tables.php` introduces three tables only:

- `office_staff`: stable ID, immutable office FK, name, position, active flag, timestamps, and a hidden normalized name/position hash. A unique office/hash index rejects accidental duplicates, including archived staff and case/whitespace variants. Different positions or offices are distinct assignments. No user/login account is created. There is no hard-delete or reassignment endpoint. Archive is idempotent and retains the row. Archived records remain editable and filterable; there is no reactivation action in this phase.
- `staff_criteria_categories`: optional office ownership (NULL means institution-wide), section number, name, timestamps and `locked_at`. A generated zero scope key enforces unique section numbers for both shared and office-owned scopes, following the existing Office criteria convention.
- `staff_criteria_questions`: category FK, text, server-assigned order, timestamps and `locked_at`. Category/order is unique. Unused categories may delete their unused questions. Referenced/locked criteria cannot be rewritten or deleted.

Existing evaluation tables, enum values, student eligibility, Office/Faculty questionnaires, scoring, reports and historic records are not migrated or reclassified. No staff evaluation submission, student staff form, staff report or administrator notification is implemented.

## Ownership and historical safety

Super Admin opens Staff from the existing Offices table and can maintain any office. Office Admin has a Staff navigation entry restricted to their freshly resolved office assignment. Nested staff IDs and submitted office contexts are checked independently. Assignment removal/reassignment takes effect on the next request. Department Admin and Student have no new access.

Staff criteria reuse the existing criteria controller behavior, board, forms and permissions. Explicit staff model bindings and separate route names prevent overlapping numeric IDs from selecting overall Office/Faculty records. Office Admin sees shared plus assigned-office criteria, can change only unused owned records, and cannot create/move shared criteria. Super Admin selects shared or office-owned scope. Question parentage and category ownership cannot be moved. Configuration writes are blocked while an active period has submissions open, consistently with existing Office criteria.

`StaffCriteriaQuestion::freezeForHistory()` atomically freezes the question and parent metadata, is idempotent, and rolls back with its enclosing transaction. HTTP checks and model events reject stale-object content edits, destructive deletes and lock removal. Category edits/deletion also check locked children. Cache invalidation occurs after commit. Model hooks protect application writes, not arbitrary privileged SQL.

Office deletion is refused while staff or private staff criteria exist. The migration rollback refuses to drop any Phase 3 table while any of the three contains records; it checks all tables before dropping anything. Empty rollback removes only the three new tables and must be paired with the matching application-code rollback. Never remove those tables while Phase 3 code is serving requests. With populated tables, disable/revert the feature code while retaining records and constraints until an authorized recovery plan exists. Do not use destructive refresh/seed commands on client databases.

## Required Phase 4 integration contract

Before implementing submissions:

1. Use a separate staff evaluation/answer namespace with restrictive FKs to `office_staff` and `staff_criteria_questions`. Never reuse overall Office evaluation uniqueness or totals for staff.
2. Validate eligibility and active staff under the submission transaction; preserve immutable office attribution. Archival must stop future eligibility while retaining previous results.
3. Snapshot evaluated name/position/office identity into the future evaluation record (or adopt an approved equivalent versioning design). Current directory details are editable; old attribution must not be reconstructed from later directory text.
4. Validate the exact questionnaire and call `freezeForHistory()` for every referenced question inside the same transaction as answers. Match the existing review-period lock order, then categories/questions in stable order; roll back freezes if submission fails. Apply restrictive answer FKs. Merely adding a future answer table does not automatically call the freeze helper.
5. Confirm whether questionnaire versions must remain fixed for an entire period, including reopening. Current maintenance allows adding unused questions after closing; it does not provide period-specific questionnaire snapshots.
6. If distinct employees can have the same normalized full name and position in one office, agree on an additional non-login identifier before relaxing duplicate protection.

No existing database, Hostinger, production service, GitHub PR or other worktree is modified. A future deployment requires independent authorization, backup/migration-state verification and isolated acceptance on the actual MySQL version, including generated-column indexes, FK behavior and concurrency. Composer authoritative maps must be regenerated from the complete source tree, including the new classes.

## Verification

Focused tests cover staff management, duplicate enforcement, archived records, position edits, actor/nested/context tampering, shared/private criteria, stale history locks, transaction rollback, generated-scope uniqueness, guarded schema rollback and synthetic future restrictive references. Inherited overall Office criteria tests and Faculty criteria, report and Review Period regression suites are included in focused verification. Frontend checks cover form payloads, permissions, saving states, errors, confirmation and role navigation. Browser checks use a new disposable synthetic SQLite runtime for actual Super Admin/Office Admin navigation, create/edit/archive, archived filtering and staff criteria writes; no client environment file or database is used. The synthetic runtime is removed afterward.

Focused combined run: **71 PHP tests passed (728 assertions)**. The subsequent staff-management run also passed (5 tests, 69 assertions), including the added office-deletion guard assertions. **16 frontend tests passed**. The local Vite production build and authoritative Composer class-resolution checks passed. The browser runtime verified actual local HTTP round trips for both administrator roles; desktop/narrow-screen checks and first-field/focus restoration were observed. Production MySQL, concurrent-writer stress and a full assistive-technology audit were not performed.

## Exact Phase 3 source manifest

New files:

- `database/migrations/2026_11_19_000001_create_office_staff_and_staff_criteria_tables.php`
- `app/Models/OfficeStaff.php`
- `app/Models/StaffCriteriaCategory.php`
- `app/Models/StaffCriteriaQuestion.php`
- `app/Models/Concerns/ProtectsStaffCriteriaHistory.php`
- `app/Http/Controllers/Admin/OfficeStaffController.php`
- `app/Http/Controllers/Admin/StaffCriteriaController.php`
- `resources/js/Pages/Admin/Offices/Staff.jsx`
- `tests/Feature/OfficeStaffManagementTest.php`
- `tests/Feature/StaffCriteriaManagementTest.php`
- `tests/Feature/OfficeStaffMigrationTest.php`
- `tests/frontend/staff.test.mjs`
- `docs/PHASE3_OFFICE_STAFF.md`

Updated files:

- `app/Models/Office.php`
- `app/Http/Controllers/Admin/OfficeController.php`
- `app/Http/Controllers/Admin/CriteriaController.php`
- `routes/web.php`
- `resources/js/Pages/Admin/Offices/Index.jsx`
- `resources/js/Layouts/AdminLayout.jsx`
- `resources/js/Pages/Admin/Criteria/Office.jsx`
- `resources/js/Pages/Admin/Criteria/Partials/CriteriaBoard.jsx`
- `resources/js/Pages/Admin/Criteria/Partials/CategoryFormModal.jsx`
- `resources/js/Pages/Admin/Criteria/Partials/QuestionFormModal.jsx`
- `resources/js/Components/ConfirmDeleteModal.jsx`

Shared form and confirmation changes add accessible names, pending-save guards and validation announcements while retaining their existing layouts and callbacks. Earlier phase files not listed here are retained without further edits. Ignored dependency/build and synthetic verification artifacts are not application source changes.
