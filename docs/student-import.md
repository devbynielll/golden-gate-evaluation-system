> **Historical workflow reference:** The current UI is documented in [Unified Student Import](UNIFIED_STUDENT_IMPORT.md). The older mode-specific/subject-column instructions below are retained for historical and legacy API context and are not instructions for the current mixed import screen.

# Student import

Admin > Department > Program > Students > Import. Accepts `.xlsx`, `.xls`, `.csv`, `.doc`, `.docx`.
Flow: **upload -> map columns -> Review (dry run, nothing saved) -> Confirm**.

## How subjects work (unchanged rules)

| Student type | Where their evaluable subjects come from | Does the import's subject column matter? |
|---|---|---|
| Regular | Their year/block (`class_subjects`) | No. Codes are ignored and flagged with a warning. |
| Irregular | `student_subject_enrollments` (per subject) | Yes. This is what the import fills. |

A subject code never enrolls anyone by itself, and nothing is assigned to students who aren't listed.
Subject codes are matched case-insensitively against the **current program's** subjects.

## Columns (you map them in the UI; header names don't matter)

| Field | New student | Existing student |
|---|---|---|
| Student ID | required | required (match key) |
| Email | required | optional; if given it must match the stored email |
| First / Last name | required | ignored (never edited) |
| Middle name, Suffix | optional | ignored |
| Year, Block | required if regular | ignored |
| Irregular? (`Y`/`N`) | blank = regular | ignored (stored value wins) |
| Subject Code(s) | required if irregular | the subjects to add |

Subjects can be given **comma/semicolon-separated in one cell** (`IT101, IT102`) or **one per row**, repeating the Student ID. Rows with the same Student ID are merged; later rows may leave other columns blank. Conflicting values for the same ID (e.g. a different email) are an error.

**Student-only files** (no subject column, as before) work unchanged: map the first columns and leave Subject Code(s) unmapped.

## Existing students

Default: a student whose ID already exists is **skipped and left unchanged** (reported under "Skipped").
Tick **"Also add subjects to students who already exist"** to add subjects to existing **irregular** students of this program. This is add-only: nobody is un-enrolled and no student fields are edited. Existing regular students are skipped (their subjects come from their block).

## Validation (all shown in Review, re-checked at import time)

- Unknown subject code -> that student is **rejected entirely** (no partial enrollment).
- Missing Student ID, bad/missing email or names, duplicate email within the file or already in use, ID already used by a non-student account.
- Irregular with no subjects; regular without year/block.
- Existing student in another program, or email not matching the existing student.
- Duplicate codes for one student -> counted once (warning). Already-enrolled subjects -> not re-added (warning).

## Result summary

Review shows counts of New students / Existing with subjects added / Skipped / Errors and total enrollments, with a per-row table (filter by clicking a count). After confirming, progress shows imported, subject enrollments, skipped, and errors with row numbers. Error rows never block valid rows.

## Samples

- `docs/samples/students-only.csv`: legacy format.
- `docs/samples/students-with-subjects.csv`: regular, irregular (comma list and one-per-row), and an existing student (`2024-0008`) getting an extra subject. Needs subjects `IT101-IT103` in the program; the last row requires the "existing students" option.

## Blocks for irregular students

An irregular student's enrollment records **which block they attend for each subject**. That decides which instructor they evaluate, so a subject taught in blocks 1-A and 1-B only shows the instructor of the block the student attends.

- Subject taught in **one** block: the block is filled in automatically.
- Subject taught in **several** blocks: you must say which. Either write `CODE:BLOCK` in the subject cell (`IT101:B`, or `IT101:1B` when the same block letter exists in several year levels), or map the optional **Subject Block** column (applies to the codes in that row). Without it the student is rejected with a message listing the available blocks.
- Subject with **no class scheduled yet**: enrolled without a block; set it later.
- A block that doesn't teach that subject is an error.
- With "add subjects to existing students" ticked, a legacy enrollment that has no block gets it filled in. An enrollment that already has a block is never moved.

Existing irregular enrollments created before blocks were recorded keep working when the subject has a single offering. If it has several, the student sees a warning instead of evaluation cards until an admin picks the block (Edit student > choose block).

Sample: `docs/samples/students-with-subjects.csv` uses `IT101:A` style blocks where a subject has more than one.

## Terms (academic year + semester)

A term is a **review period** (e.g. "2026-2027 1st Semester"). Class offerings (block + subject + instructor) and irregular students' subject enrollments each belong to one term, and students only ever see and evaluate the **active** term.

- **Import / admin form** record enrollments in the **active term** (the review shows which). With no active term, enrolling subjects is refused.
- **Classes tab** has a term selector. Scheduling a non-active term is allowed (to prepare ahead); students don't see it until it is activated.
- **Starting a new term:** create the review period, open Classes, choose it, and use **Copy schedule from** the previous term. It only fills gaps. Student enrollments are *not* copied: re-import (or edit) irregular students for the new term. Regular students follow their block automatically.
- **Evaluation history** is per term and is never changed by rolling over. A class's subject or instructor can't be changed once evaluations were submitted for it that term (the page says so).
- **Existing data** was assigned to the active term (else the latest) when this was introduced; if no term existed yet, the first one created adopts it.
- A student's own year/block (`class_section_id`) is still a single current value, not per term. Update it (or re-import) when students advance a year.
