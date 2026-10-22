# local_quicktools — Quick tools for Moodle

Small quality-of-life fixes for everyday course and quiz work.

- **Edit mode keeps your place.** Toggling *Edit mode* reloads the page; this remembers the
  section/activity/question at the top of the screen and scrolls back to it afterwards.
- **Bulk marks in quizzes.** On a quiz's *Questions* page, click *Set marks for several questions*, tick the
  questions, type a mark and press *Set marks for selected*. Grades are recalculated once for the batch.
  Works on quizzes that already have attempts. Needs `mod/quiz:manage`.
- **Quiz review window.** In a quiz's settings, the *Review window* section lets you choose when students can review
  their attempt after the quiz has closed. You choose what they get with the normal *Review options → After the quiz is
  closed* column (or a preset). Outside the window a scheduled task hides those options (so students cannot review) and
  puts them back when it opens, within about a minute; the form always shows your choice. The quiz needs a close date.

- **Review option presets.** On the quiz settings page, a bar at the top of *Review options* applies a whole preset
  (all four columns, 28 boxes) in one click. Built-in presets: Moodle default, Practice, Exam (everything after close),
  Exam (attempt and marks after close), No review. *Save current options as a preset…* stores your own under a name,
  and *Use for new quizzes* makes one the default that new quizzes start from. Presets are per teacher.

Bulk move/hide of sections and activities is not included: Moodle has a built-in *Bulk edit* mode.

Works on Moodle 4.2+ (footer callback and `coursemodule_*` callbacks in `lib.php`; hook in `db/hooks.php` for 4.4+).
The JavaScript in `amd/build` is a plain copy of `amd/src` (no build step needed).
