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
 * Single-player attempt helper logic for mod_crossduel.
 *
 * @package    mod_crossduel
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crossduel\local;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Single-player attempt helper logic for Cross Duel.
 */
class attempt_manager {

    public static function get_or_create_attempt(int $crossduelid, int $userid): stdClass {
        global $DB;

        $attempt = $DB->get_record('crossduel_attempt', [
            'crossduelid' => $crossduelid,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);

        if ($attempt) {
            return $attempt;
        }

        $attempt = new stdClass();
        $attempt->crossduelid = $crossduelid;
        $attempt->userid = $userid;
        $attempt->status = 'inprogress';
        $attempt->timecreated = time();
        $attempt->timemodified = time();

        $attempt->id = $DB->insert_record('crossduel_attempt', $attempt);

        return $attempt;
    }

    public static function get_solved_word_ids(int $attemptid): array {
        global $DB;

        $records = $DB->get_records('crossduel_attempt_word', [
            'attemptid' => $attemptid,
            'issolved' => 1,
        ]);

        $wordids = [];

        foreach ($records as $record) {
            $wordids[(int)$record->wordid] = true;
        }

        return $wordids;
    }

    public static function store_attempt_word(int $attemptid, int $wordid, string $useranswer, bool $correct): void {
        global $DB;

        $record = $DB->get_record('crossduel_attempt_word', [
            'attemptid' => $attemptid,
            'wordid' => $wordid,
        ], '*', IGNORE_MISSING);

        if ($record) {
            $record->useranswer = $useranswer;
            $record->timeanswered = time();

            if ($correct) {
                $record->issolved = 1;
            }

            $DB->update_record('crossduel_attempt_word', $record);
            return;
        }

        $record = new stdClass();
        $record->attemptid = $attemptid;
        $record->wordid = $wordid;
        $record->issolved = $correct ? 1 : 0;
        $record->useranswer = $useranswer;
        $record->timeanswered = time();

        $DB->insert_record('crossduel_attempt_word', $record);
    }

    public static function update_attempt_completion(stdClass $attempt, array $layoutrows, array $solvedwordids): void {
        global $DB;

        $requiredwordids = [];

        foreach ($layoutrows as $row) {
            $requiredwordids[(int)$row->wordid] = true;
        }

        $allsolved = true;

        foreach ($requiredwordids as $wordid => $unused) {
            if (!isset($solvedwordids[$wordid])) {
                $allsolved = false;
                break;
            }
        }

        $newstatus = $allsolved ? 'completed' : 'inprogress';

        if ($attempt->status !== $newstatus) {
            $attempt->status = $newstatus;
            $attempt->timemodified = time();

            $DB->update_record('crossduel_attempt', $attempt);
        }
    }
}