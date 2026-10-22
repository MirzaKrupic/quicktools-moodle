# local_quicktools — Quick tools for Moodle

Small quality-of-life fixes for everyday course editing.

- **Edit mode keeps your place.** Toggling *Edit mode* reloads the page; this remembers the
  section/activity/question at the top of the screen and scrolls back to it afterwards.
- **Bulk marks in quizzes.** On a quiz's *Questions* page, click *Set marks for several questions*, tick the
  questions, type a mark and press *Set marks for selected*. Grades are recalculated once for the batch.
  Needs `mod/quiz:manage`.

Bulk move/hide of sections and activities is not included: Moodle 4.3+ has a built-in *Bulk edit* mode.

Works on Moodle 4.2+ (footer callback in `lib.php`; hook in `db/hooks.php` for 4.4+).
The JavaScript in `amd/build` is a plain copy of `amd/src` (no build step needed).
