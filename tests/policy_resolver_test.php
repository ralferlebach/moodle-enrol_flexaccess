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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for FlexAccess policy precedence.
 *
 * @package    enrol_flexaccess
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_flexaccess;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Policy resolver tests.
 *
 * @package    enrol_flexaccess
 */
#[CoversClass(\enrol_flexaccess\local\policy_resolver::class)]
final class policy_resolver_test extends \advanced_testcase {
    /**
     * Test that child scope cannot widen a prohibition by default.
     */
    public function test_prohibition_is_not_widened_by_default(): void {
        $this->assertFalse(\enrol_flexaccess\local\policy_resolver::merge_permission(false, true));
        $this->assertTrue(\enrol_flexaccess\local\policy_resolver::merge_permission(false, true, true));
    }

    /**
     * Test temporary access-key scope inheritance.
     */
    public function test_temporary_access_key_scope(): void {
        $resolver = \enrol_flexaccess\local\policy_resolver::class;
        $this->assertSame('none', $resolver::temporary_access_key_scope(false, 'inherit'));
        $this->assertSame('system', $resolver::temporary_access_key_scope(true, 'inherit'));
        $this->assertSame('course', $resolver::temporary_access_key_scope(false, 'course'));
        $this->assertSame('course', $resolver::temporary_access_key_scope(true, 'course'));
    }

    /**
     * Test participant visibility inheritance.
     */
    public function test_participant_list_access_inheritance(): void {
        $this->assertSame('hide', \enrol_flexaccess\local\policy_resolver::participant_list_access('hide', 'inherit'));
        $this->assertSame('show', \enrol_flexaccess\local\policy_resolver::participant_list_access('hide', 'show'));
    }
}
