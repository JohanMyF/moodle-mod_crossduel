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
 * Presence and partner discovery helper logic for mod_crossduel.
 *
 * @package    mod_crossduel
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crossduel\local;

defined('MOODLE_INTERNAL') || die();

use context_module;
use stdClass;

/**
 * Presence and partner discovery helper logic for Cross Duel.
 */
class presence_manager {

    public static function touch_presence(int $crossduelid, int $userid): void {
        global $DB;

        $record = $DB->get_record('crossduel_presence', [
            'crossduelid' => $crossduelid,
            'userid' => $userid,
        ], '*', IGNORE_MISSING);

        if ($record) {
            $record->lastseen = time();
            $DB->update_record('crossduel_presence', $record);
            return;
        }

        $record = new stdClass();
        $record->crossduelid = $crossduelid;
        $record->userid = $userid;
        $record->lastseen = time();

        $DB->insert_record('crossduel_presence', $record);
    }

    public static function get_presence_label(int $lastseen): string {
        return get_string('presence_lastseen', 'crossduel', userdate($lastseen));
    }

    public static function user_has_passed_activity(stdClass $crossduel, int $userid): bool {
        $percentage = crossduel_get_user_solved_percentage((int)$crossduel->id, $userid);
        $passpercentage = isset($crossduel->passpercentage) ? (float)$crossduel->passpercentage : 60.0;

        return $percentage >= $passpercentage;
    }

    public static function get_available_multiplayer_partners(
        stdClass $crossduel,
        stdClass $course,
        context_module $context,
        int $currentuserid
    ): array {
        global $DB;

        $cutoff = time() - 180;

        $presence = $DB->get_records_select(
            'crossduel_presence',
            'crossduelid = ? AND lastseen >= ?',
            [(int)$crossduel->id, $cutoff],
            'lastseen DESC'
        );

        if (!$presence) {
            return [];
        }

        $users = get_enrolled_users($context, 'mod/crossduel:play');

        $usersbyid = [];
        foreach ($users as $user) {
            $usersbyid[(int)$user->id] = $user;
        }

        $available = [];

        foreach ($presence as $record) {
            $userid = (int)$record->userid;

            if ($userid === $currentuserid) {
                continue;
            }

            if (!isset($usersbyid[$userid])) {
                continue;
            }

            $user = $usersbyid[$userid];

            if (!empty($user->deleted) || !empty($user->suspended)) {
                continue;
            }

            if (!has_capability('mod/crossduel:play', $context, $userid)) {
                continue;
            }

            if (self::user_has_passed_activity($crossduel, $userid)) {
                continue;
            }

            if (multiplayer_manager::user_is_busy((int)$crossduel->id, $userid)) {
                continue;
            }

            $user->crossduel_lastactive = self::get_presence_label((int)$record->lastseen);
            $available[] = $user;
        }

        usort($available, function($a, $b) {
            return strcmp(fullname($a), fullname($b));
        });

        return $available;
    }
}