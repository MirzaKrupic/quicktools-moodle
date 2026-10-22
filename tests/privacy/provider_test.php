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

namespace local_quicktools\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_quicktools\local\review_presets;

/**
 * Privacy tests: teachers' saved review presets and default.
 *
 * @package   local_quicktools
 * @category  test
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_quicktools\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Two users, the first with a preset and a default.
     *
     * @return \stdClass[]
     */
    protected function users(): array {
        $this->resetAfterTest();
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        review_presets::save($one->id, 'One preset', ['closed' => ['attempt' => 1]]);
        review_presets::set_default($one->id, 'b:showall');
        review_presets::save($two->id, 'Two preset', []);
        return [$one, $two];
    }

    public function test_metadata(): void {
        $collection = provider::get_metadata(new collection('local_quicktools'));
        $items = $collection->get_collection();
        $this->assertCount(2, $items);
        $this->assertSame('local_quicktools_revpreset', $items[0]->get_name());
    }

    public function test_contexts_for_user(): void {
        [$one] = $this->users();
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertEquals([\context_system::instance()->id], provider::get_contexts_for_userid($one->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($nobody->id)->get_contextids());
    }

    public function test_export(): void {
        [$one] = $this->users();
        $context = \context_system::instance();
        $this->export_context_data_for_user($one->id, $context, 'local_quicktools');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'local_quicktools')]);
        $this->assertCount(1, $data->review_option_presets);
        $this->assertSame('One preset', $data->review_option_presets[0]->name);
        $prefs = $writer->get_user_preferences('local_quicktools');
        $this->assertSame('b:showall', $prefs->{review_presets::PREF}->value);
    }

    public function test_delete_for_one_user(): void {
        global $DB;
        [$one, $two] = $this->users();
        $context = \context_system::instance();
        provider::delete_data_for_user(new approved_contextlist($one, 'local_quicktools', [$context->id]));
        $this->assertSame(0, $DB->count_records('local_quicktools_revpreset', ['userid' => $one->id]));
        $this->assertSame('', review_presets::get_default($one->id));
        $this->assertSame(1, $DB->count_records('local_quicktools_revpreset', ['userid' => $two->id]));
    }

    public function test_delete_everything_in_the_system_context(): void {
        global $DB;
        $this->users();
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, $DB->count_records('local_quicktools_revpreset'));
        $this->assertSame(0, $DB->count_records('user_preferences', ['name' => review_presets::PREF]));
    }

    public function test_userlist(): void {
        global $DB;
        [$one, $two] = $this->users();
        $context = \context_system::instance();
        $userlist = new userlist($context, 'local_quicktools');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $userlist->get_userids());

        provider::delete_data_for_users(new approved_userlist($context, 'local_quicktools', [$one->id]));
        $this->assertSame(0, $DB->count_records('local_quicktools_revpreset', ['userid' => $one->id]));
        $this->assertSame(1, $DB->count_records('local_quicktools_revpreset', ['userid' => $two->id]));
    }
}
