# Department Admin — Faculty Evaluation Criteria

Department Admin can view faculty Criteria across departments using the existing Criteria board. The native Faculty Criteria navigation link opens the same categories, question table and modals used by Super Admin. This grants no Office Criteria, account, office-management or review-period administration access. Office Admin and Student permissions are unchanged. No schema, dependency, framework or evaluation/reporting calculation is changed.

## Safe maintenance

Department Admin may create categories/questions and edit/delete unused faculty questions/categories only when no active review period has submissions open. During an open submission window the board is read-only and Laravel rejects every Department Admin Criteria write, including direct/forged requests. The existing Super Admin manages submission status; this revision does not grant Department Admin that privilege.

A question with any evaluation-answer reference cannot be modified or deleted by Department Admin. Its parent category cannot be renamed, renumbered or deleted. This includes historical periods and answer references whose evaluation has no submitted timestamp. Unused questions in such a category may still be maintained when submissions are closed; a new question may be added without rewriting the existing questions or answers. Request payloads cannot move questions/categories into Office Criteria or change stored question order: type and parent come from validated route context, and only existing name/section/text inputs are accepted.

Used records are identified on the server for UI locks, separately from the shared board cache. Backend checks run again on every write. Attempts rejected after a stale page receive a visible criteria validation message. Nested category/type and question/category IDs are checked independently.

## Active-period impact and operating limits

Criteria are global by evaluatee type; there is no period-specific questionnaire version. The existing student service reads the current faculty question set and submission validation requires every current question. Successful maintenance flushes the existing caches. Adding a question does not backfill or alter any earlier evaluation answer. It changes the questionnaire for future submissions, including when the same review period is reopened. Already-open stale forms must reload to obtain the new question set.

Adding questions can make questionnaires differ between earlier and later respondents. Reports retain their existing average-of-stored-ratings calculation, so differing question counts/content can affect comparability and the relative contribution of later answers. Configure the complete questionnaire before opening a new period; do not reopen a partly answered period with a changed set unless the authorized owner accepts that impact. Closing submissions is not itself questionnaire versioning. This revision blocks Department Admin writes while submissions are open rather than introducing mid-window replacement or implicit answer conversion.

For maintenance, allow in-flight evaluation submissions to finish after closing the window. Real MySQL concurrency/lock-contention behavior has not been certified by the disposable SQLite tests; the new checks are not a general concurrent-writer/versioning guarantee. No evaluation-submission implementation was changed.

Super Admin retains its existing editing/deletion behavior, including editing already-used question text or category labels. Such edits can change the interpretation of historical answers; the existing database foreign key restricts deletion of referenced questions. This existing risk is documented, not silently changed for Super Admin. A broader immutability/versioning policy requires separate authorization.

## Verification scope

Tests cover all four roles, Office/type/nested-ID tampering, used and unused records, the open-submission guard, unchanged historical answers/reports, cache separation and evaluation submission using newly added questions. Verification uses only new disposable synthetic SQLite environments. No client/MAMP database, previous preview or production service is used. Student Import regular-only policy and other outstanding client decisions remain unchanged.
