# Subject and Class imports

Open a program, select **Subjects** or **Classes**, then **Import**. This uses the same upload → column mapping/preview → import results modal as Faculty imports. Super Admin and Department Admin can import; other roles cannot. CSV, XLSX and XLS are accepted (5 MB maximum, 2,000 rows including the header). Map columns explicitly and choose the first data row; headerless files work with data starting at row 1. Select the worksheet to import as the active sheet before saving a workbook. Files must match their extensions. No Word import is provided for these academic templates.

Use [subjects.csv](import-templates/subjects.csv) and [classes.csv](import-templates/classes.csv) as examples; replace sample codes and IDs with values from your program.

## Subjects

Required columns: **Subject Code** (max 20 characters), **Description** (max 255), **Year Level** (integer 1–6). The current workspace supplies the program. No Credits/Units column is required or mapped.

Only new subjects are created. Identical existing subjects are skipped without touching IDs, credits, timestamps or references. Existing codes with different descriptions/years are errors; use manual Edit for intentional corrections. If the same code occurs with different details within one upload, all rows for that code fail. Identical duplicates create at most one record and skip subsequent repeats. Codes are compared without case when finding duplicates; original spelling is preserved for new records. Blank or incomplete data rows produce row errors.

## Classes and professor assignments

The normal Classes interface shows a read-only **Active period** label. TERM and Copy Schedule From dropdowns are removed. Create, edit, delete and import requests are limited to the currently active review period. No active period means scheduling/import writes are blocked with a message asking the Super Admin to activate a period; the latest historical period is never selected as a write fallback. The workspace also avoids loading active student-enrollment pivots when no period is active, so the no-active message remains readable even with existing regular/irregular students; stored enrollment history is untouched. Manual requests without a period retain the existing active-period default; explicit inactive/changed period IDs are rejected. Class uploads continue to require explicit `termId`, and their tokens remain bound to that active period. A period change requires reloading and re-uploading; changing an old token's period is rejected.

The selected period is rechecked under a database row lock inside each Class write transaction, including every import row, before section/offering locks. A period change between review and execution produces an error rather than retargeting the schedule. Real MySQL concurrency and lock-contention verification remains pending; isolated SQLite tests do not certify concurrent behavior.

Existing `?term=ID` workspace links can still view historical schedules. Their existing Class form is read-only, with no save action, and normal backend requests reject historical period context. Reports retain their period filters. A section with historical (or unassigned-period) offerings cannot be globally renamed or deleted through normal Class operations, even if no evaluation exists; active-period offerings may still be maintained independently. Evaluated offerings retain their previous instructor/subject/identity protections. No period relationships, schedules, enrollments, evaluations or answers are migrated or deleted by this revision.

The old authorized Schedule Copy backend remains unchanged for compatibility, including its existing explicit source/target contract. No copy control or replacement scheduling feature is exposed in the normal interface. This compatibility endpoint is separate from the active-only normal Class write paths; existing role restrictions remain. No new professor cross-program or irregular-student policy is introduced.

One row represents **one subject offering**, not an entire class replacement:

- **Year Level**: integer 1–6.
- **Block / Section**: existing-style block value, such as A, up to 10 characters; do not enter a combined program/year/class label.
- **Subject Code**: an existing subject in this program.
- **Faculty ID**: optional existing directory ID in this program, not a name or internal numeric key. Blank, unmapped, or the reserved value `TBA` means unassigned. To assign a directory record literally named ID `TBA`, use manual Edit instead.

The current program + year + block identifies the section. An absent section is created only when a valid offering can be saved. A section's year is not required to match a subject's year, consistent with manual Class editing. Faculty qualifications do not restrict teaching assignments. One professor can teach many subjects/classes. Different professors per subject and TBA can be mixed.

Imports **only add missing offerings** in the selected period. They never delete offerings, change section identity, reassign existing professors, fill existing TBA, or modify enrollments/evaluations/answers. Identical existing offerings are skipped, including evaluated offerings. Any existing different professor/TBA is an error even if unevaluated; review it with manual Edit. Other periods remain untouched. Conflicting professor assignments for one class/subject in the same file all fail, including TBA versus assigned. Independent valid rows may still import. Repeating a completed file is safe.

## Results and safety

Preview/mapping does not write academic records. Import is an explicit confirmation; valid rows commit independently in transactions, invalid rows fail without partial row changes. Results show physical spreadsheet row numbers and counts of imported, skipped and failed rows (up to 200 error/skip details, matching the existing batch infrastructure). Class successes count newly added offerings, not class sections. No whole-file rollback is promised. A failure in one row does not remove earlier successful independent rows.

These bounded imports run synchronously in one job, so no additional queue worker is needed. The existing progress/result polling reads the completed ImportBatch. Uploads expire after 30 minutes and are bound to the uploading administrator, program, import type and (for Classes) term. Used tokens cannot be replayed. Duplicate column mappings and out-of-file mappings are rejected before creating a batch.

Import rows acquire a program lock, then section/offering locks for Classes. The section/offering order matches manual Class editing and evaluation submission. Existing uniqueness/foreign-key constraints remain authoritative; a write conflict rolls back that row and reports an error. Real concurrent InnoDB lock-contention testing remains separate from the isolated automated suite.
