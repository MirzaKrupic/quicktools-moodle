# local_quicktools — Quick tools for Moodle

Small quality-of-life fixes for everyday course and quiz work.

- **Edit mode keeps your place.** Toggling *Edit mode* reloads the page; this remembers the
  section/activity/question at the top of the screen and scrolls back to it afterwards.
- **Bulk marks in quizzes.** On a quiz's *Questions* page, click *Set marks for several questions*, tick the
  questions, type a mark and press *Set marks for selected*. Grades are recalculated once for the batch.
  Works on quizzes that already have attempts. Needs `mod/quiz:manage`.
- **Quiz review window.** In a quiz's settings, the *Review window* section lets you choose when students can review
  their attempt after the quiz has closed, and exactly which review options they get (the attempt, marks, right
  answer, feedback...). Outside the window students cannot review; a scheduled task opens and closes it
  (within about a minute). While a schedule is on, Moodle's own "After the quiz is closed" boxes are driven by it;
  turning the schedule off leaves your chosen options there. The quiz needs a close date.

Bulk move/hide of sections and activities is not included: Moodle has a built-in *Bulk edit* mode.

Works on Moodle 4.2+ (footer callback and `coursemodule_*` callbacks in `lib.php`; hook in `db/hooks.php` for 4.4+).
The JavaScript in `amd/build` is a plain copy of `amd/src` (no build step needed).
