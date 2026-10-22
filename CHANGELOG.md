# Changelog

## 0.5 (2026-10-22)
- Works on Moodle 4.2 to 4.5 (tested on 4.2.11 and 4.5.15). Handles the new "Maximum marks" review option of 4.4+.
- Review window: backup, restore and quiz duplication now keep the schedule; uninstalling gives quizzes their
  review options back.
- Privacy API: saved review presets and the default preset can be exported and deleted.
- JavaScript is now written as ES modules and built with Moodle's own tooling.
- Automated tests (PHPUnit) and Moodle coding standard clean.
- Review option presets are loaded through a web service (Moodle 4.3+ limits data passed to page scripts).

## 0.4
- Students see a notice on the quiz page about when they can review (review window).

## 0.3
- Review option presets: built-in presets, your own presets, and a default for new quizzes.

## 0.2
- Quiz review window: choose when students can review a quiz after it closes.

## 0.1
- Keep your place when the Edit mode switch is used.
- Set the marks of several quiz questions at once.
