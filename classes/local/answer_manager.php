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
 * Answer helper logic for mod_crossduel.
 *
 * @package    mod_crossduel
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crossduel\local;

defined('MOODLE_INTERNAL') || die();

use core_text;
use stdClass;

/**
 * Answer helper logic for Cross Duel.
 */
class answer_manager {

    public static function normalize_answer(string $answer): string {
        $normalized = core_text::strtoupper(trim($answer));
        return preg_replace('/[^[:alnum:]]/u', '', $normalized);
    }

    public static function find_target_row(array $layoutrows, int $wordid): ?stdClass {
        foreach ($layoutrows as $row) {
            if ((int)$row->wordid === $wordid) {
                return $row;
            }
        }

        return null;
    }

    public static function is_correct(string $submittedanswer, stdClass $targetrow): bool {
        return self::normalize_answer($submittedanswer) === (string)$targetrow->normalizedword;
    }
}