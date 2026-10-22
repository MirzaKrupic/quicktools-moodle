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

namespace local_quicktools\external;

use core_external\external_api;

/**
 * Tests for the preset web services.
 *
 * @package   local_quicktools
 * @category  test
 * @copyright 2026 Mirza Krupic
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_quicktools\external\review_preset_api
 */
final class review_preset_api_test extends \advanced_testcase {
    /**
     * A teacher and a student in one course.
     *
     * @return \stdClass[] teacher, student
     */
    protected function people(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        return [$teacher, $student];
    }

    /**
     * Call a service the way the page does and clean the result.
     *
     * @param string $method
     * @param array $args
     * @return array
     */
    protected function call(string $method, array $args = []): array {
        $result = review_preset_api::$method(...$args);
        $returns = review_preset_api::{$method . '_returns'}();
        return external_api::clean_returnvalue($returns, $result);
    }

    public function test_list_has_built_ins_and_own_presets(): void {
        [$teacher] = $this->people();
        $this->setUser($teacher);
        $result = $this->call('list');
        $this->assertSame(['b:showall', 'b:noreview', 'b:afterclose'], array_column($result['presets'], 'ref'));
        $this->assertSame('', $result['default']);
        $this->assertIsArray(json_decode($result['presets'][0]['config'], true));
    }

    public function test_save_default_and_delete(): void {
        [$teacher] = $this->people();
        $this->setUser($teacher);
        $config = json_encode(['closed' => ['attempt' => 1, 'marks' => 1]]);

        $result = $this->call('save', ['My lab preset', $config]);
        $own = array_values(array_filter($result['presets'], fn($p) => !$p['builtin']));
        $this->assertCount(1, $own);
        $this->assertSame('My lab preset', $own[0]['name']);

        $result = $this->call('set_default', [$own[0]['ref']]);
        $this->assertSame($own[0]['ref'], $result['default']);

        $result = $this->call('delete', [(int) substr($own[0]['ref'], 2)]);
        $this->assertSame('', $result['default']);
        $this->assertCount(3, $result['presets']);
    }

    public function test_bad_data_is_rejected(): void {
        [$teacher] = $this->people();
        $this->setUser($teacher);
        $this->expectException(\invalid_parameter_exception::class);
        review_preset_api::save('Name', 'not json');
    }

    public function test_unknown_default_is_rejected(): void {
        [$teacher] = $this->people();
        $this->setUser($teacher);
        $this->expectException(\invalid_parameter_exception::class);
        review_preset_api::set_default('u:123456');
    }

    public function test_students_cannot_use_presets(): void {
        [, $student] = $this->people();
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        review_preset_api::list();
    }

    public function test_students_cannot_save_presets(): void {
        [, $student] = $this->people();
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        review_preset_api::save('Sneaky', '{}');
    }
}
