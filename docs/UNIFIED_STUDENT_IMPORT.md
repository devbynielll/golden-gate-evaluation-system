# Unified Student Import

Current UI: Departments → select department/program → Students → Import Students. The same import accepts both Regular and Irregular rows; no two-pass import is required.

Preserve and map the nine client columns in order: **STUDENT ID, EMAIL, FIRST NAME, LAST NAME, MIDDLE NAME, SUFFIX, YEAR LEVEL, BLOCK, IRREGULAR (Y/N)**. The Irregular mapping is required in mixed mode. N or a blank cell means Regular; Y means Irregular. The backend also retains its existing boolean aliases. Invalid flags fail validation.

Upload → map columns/start row → Review → Confirm Import → completion counts. Review writes no academic records. New students need a unique ID/email and first/last names; middle name and suffix are optional. Regular students need year 1–6 and a block up to 10 characters. Their existing section/offering logic supplies faculty evaluation eligibility; a new empty section does not invent teaching assignments.

Irregular students are created with no automatically inferred home section or subject enrollments, even when year/block cells are populated. They appear under Irregular Students. After import, use Edit Student to explicitly select valid attended subjects and class sections for the active period. Faculty evaluation eligibility follows those valid offerings, not the spreadsheet's year/block. Subject Codes, Subject Block and update-existing controls remain absent from this UI.

Existing students are not edited, reclassified, re-enrolled or sent replacement account credentials by reimport. Existing program/email conflict guards remain. Identical repeats are grouped; conflicting duplicates fail. Valid independent students can be saved while invalid students fail; account/student/counter writes are transactional per record. Review and completion distinguish Regular and Irregular creations. Counts may represent grouped students rather than raw spreadsheet rows.

Student and Faculty processing requires both imports and default queues: imports for row jobs and default for account mail/reminders. A queued notice is not proof of email delivery. Progress retries temporary network failures, stops on terminal results, reports expired authorization, and reconciles committed counters if the final callback was interrupted. An interrupted import can already contain saved records: inspect counts before retrying.

Upload tokens are tied to administrator/program/mode. Existing regular-only, irregular-only and authorized legacy API contracts remain for compatibility; do not reuse a mixed token under another mode. The normal mixed UI prohibits subject mappings and updateExisting. Never switch to legacy API semantics to infer irregular enrollment.

The additive migration 2026_11_01_000003 adds regular_count and irregular_count to import_batches. Existing historical batches default to zero; no student/evaluation data is rewritten. Review migration status and take a verified backup before any separately authorized deployment. This source synchronization does not run production migrations.
