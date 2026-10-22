<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_quicktools\local;

/**
 * Tests for review option presets.
 *
 * @package   local_quicktools
 * @category  test
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_quicktools\local\review_presets
 */
final class review_presets_test extends \advanced_testcase {
    public function test_there_are_three_built_in_presets(): void {
        $this->resetAfterTest();
        $builtin = review_presets::builtin();
        $this->assertSame(['b:showall', 'b:noreview', 'b:afterclose'], array_column($builtin, 'ref'));
        foreach ($builtin as $preset) {
            $this->assertTrue($preset['builtin']);
            $this->assertNotEmpty($preset['name']);
            $this->assertSame(review_presets::WHEN, array_keys($preset['config']));
            foreach ($preset['config'] as $options) {
                $this->assertSame(review_presets::options(), array_keys($options));
            }
        }
    }

    public function test_built_in_contents(): void {
        $this->resetAfterTest();
        $byref = array_column(review_presets::builtin(), 'config', 'ref');

        $this->assertSame(1, $byref['b:showall']['closed']['marks']);
        $this->assertSame(array_fill_keys(review_presets::options(), 1), $byref['b:showall']['immediately']);

        // Turn off all review: only "the attempt" while taking the quiz.
        $this->assertSame(1, $byref['b:noreview']['during']['attempt']);
        $this->assertSame(0, array_sum($byref['b:noreview']['during']) - 1);
        foreach (['immediately', 'open', 'closed'] as $when) {
            $this->assertSame(0, array_sum($byref['b:noreview'][$when]));
        }

        // Open after the quiz is closed: nothing before, everything after.
        foreach (['immediately', 'open'] as $when) {
            $this->assertSame(0, array_sum($byref['b:afterclose'][$when]));
        }
        $this->assertSame(array_fill_keys(review_presets::options(), 1), $byref['b:afterclose']['closed']);
    }

    public function test_save_list_and_replace(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $grid = ['closed' => ['attempt' => 1, 'marks' => 1, 'bogus' => 1], 'nonsense' => ['attempt' => 1]];

        review_presets::save($user->id, '  My preset  ', $grid);
        $mine = review_presets::for_user($user->id);
        $this->assertCount(1, $mine);
        $this->assertSame('My preset', $mine[0]['name']);
        $this->assertFalse($mine[0]['builtin']);
        $this->assertSame(1, $mine[0]['config']['closed']['marks']);
        $this->assertSame(0, $mine[0]['config']['closed']['rightanswer']);
        $this->assertArrayNotHasKey('nonsense', $mine[0]['config']);
        $this->assertArrayNotHasKey('bogus', $mine[0]['config']['closed']);

        // Saving under the same name replaces it.
        review_presets::save($user->id, 'My preset', ['closed' => ['attempt' => 1]]);
        $mine = review_presets::for_user($user->id);
        $this->assertCount(1, $mine);
        $this->assertSame(0, $mine[0]['config']['closed']['marks']);
    }

    public function test_a_name_is_required(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->expectException(\invalid_parameter_exception::class);
        review_presets::save($user->id, '   ', []);
    }

    public function test_presets_belong_to_one_teacher(): void {
        global $DB;
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        review_presets::save($one->id, 'Mine', ['closed' => ['attempt' => 1]]);
        review_presets::save($two->id, 'Mine', ['closed' => ['attempt' => 0]]);

        $this->assertCount(1, review_presets::for_user($one->id));
        $this->assertCount(1, review_presets::for_user($two->id));
        $this->assertSame(1, review_presets::for_user($one->id)[0]['config']['closed']['attempt']);

        // One teacher cannot delete the other's preset.
        $id = (int) substr(review_presets::for_user($two->id)[0]['ref'], 2);
        review_presets::delete($one->id, $id);
        $this->assertCount(1, review_presets::for_user($two->id));
        review_presets::delete($two->id, $id);
        $this->assertCount(0, review_presets::for_user($two->id));
        $this->assertSame(1, $DB->count_records('local_quicktools_revpreset'));
    }

    public function test_default_preset(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame('', review_presets::get_default($user->id));

        review_presets::set_default($user->id, 'b:noreview');
        $this->assertSame('b:noreview', review_presets::get_default($user->id));
        $this->assertSame('', review_presets::get_default($other->id), 'per teacher');

        review_presets::save($user->id, 'Mine', []);
        $ref = review_presets::for_user($user->id)[0]['ref'];
        review_presets::set_default($user->id, $ref);
        $this->assertSame($ref, review_presets::get_default($user->id));

        // Deleting the default preset clears the default.
        review_presets::delete($user->id, (int) substr($ref, 2));
        $this->assertSame('', review_presets::get_default($user->id));

        review_presets::set_default($user->id, 'b:showall');
        review_presets::set_default($user->id, '');
        $this->assertSame('', review_presets::get_default($user->id));
    }

    public function test_default_must_exist_and_belong_to_the_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        review_presets::save($other->id, 'Theirs', []);
        $theirs = review_presets::for_user($other->id)[0]['ref'];

        foreach (['b:nonexistent', 'u:99999', $theirs, 'garbage'] as $bad) {
            try {
                review_presets::set_default($user->id, $bad);
                $this->fail("accepted $bad");
            } catch (\invalid_parameter_exception $e) {
                $this->assertSame('', review_presets::get_default($user->id));
            }
        }
    }

    public function test_a_removed_built_in_default_counts_as_no_default(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(review_presets::PREF, 'b:practice', $user->id);
        $this->assertSame('', review_presets::get_default($user->id));
    }
}
