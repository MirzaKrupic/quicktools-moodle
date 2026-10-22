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
  Students see a notice on the quiz page saying when review opens and closes and what they will be able to see.
  The Review window section also has a preset picker and checkboxes for those options, kept in step with the real column.

- **Review option presets.** On the quiz settings page, a bar at the top of *Review options* applies a whole preset
  (all four columns, 28 boxes) in one click. Built-in presets: Show everything, Turn off all review, and Open after the quiz is closed (everything). *Save current options as a preset…* stores your own under a name,
  and *Use for new quizzes* makes one the default that new quizzes start from. Presets are per teacher.

Bulk move/hide of sections and activities is not included: Moodle has a built-in *Bulk edit* mode.

## Compatibility

Moodle 4.2 to 4.5 (tested on 4.2.11 and 4.5.15). The footer callback in `lib.php` serves 4.2 and 4.3;
`db/hooks.php` serves 4.4 and later.

## Development

- JavaScript source is in `amd/src` (ES modules); build with `npx grunt amd` from a Moodle checkout.
- Tests: `vendor/bin/phpunit --testsuite local_quicktools_testsuite` (PHPUnit environment required).
- Code style: `phpcs --standard=moodle`.

## Notes

- Uninstalling restores the quiz review options that a closed review window had hidden.
- Backing up, restoring or duplicating a quiz keeps its review window.
- Saved presets are personal data and are covered by Moodle's privacy API.

Licence: GNU GPL v3 or later (see `LICENSE`).
