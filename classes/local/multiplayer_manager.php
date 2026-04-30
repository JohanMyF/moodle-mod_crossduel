<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Multiplayer helper logic for mod_crossduel.
 *
 * @package    mod_crossduel
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crossduel\local;

defined('MOODLE_INTERNAL') || die();

use context_module;
use core_user;
use stdClass;

/**
 * Multiplayer helper logic for Cross Duel.
 */
class multiplayer_manager {

    public static function get_user_current_game(int $crossduelid, int $userid): ?stdClass {
        global $DB;

        $sql = "
            SELECT *
              FROM {crossduel_game}
             WHERE crossduelid = :crossduelid
               AND (playera = :userid1 OR playerb = :userid2)
               AND status IN ('invited', 'active', 'completed')
          ORDER BY id DESC
        ";

        $records = $DB->get_records_sql($sql, [
            'crossduelid' => $crossduelid,
            'userid1' => $userid,
            'userid2' => $userid,
        ], 0, 1);

        if (!$records) {
            return null;
        }

        return reset($records);
    }

    public static function get_incoming_invites(int $crossduelid, int $userid): array {
        global $DB;

        return $DB->get_records('crossduel_game', [
            'crossduelid' => $crossduelid,
            'playerb' => $userid,
            'status' => 'invited',
        ], 'id DESC');
    }

    public static function user_is_busy(int $crossduelid, int $userid): bool {
        return self::get_user_current_game($crossduelid, $userid) !== null;
    }

    public static function create_invitation(stdClass $crossduel, int $fromuserid, int $touserid): void {
        global $DB;

        $game = new stdClass();
        $game->crossduelid = (int)$crossduel->id;
        $game->playera = $fromuserid;
        $game->playerb = $touserid;
        $game->horizontalplayer = 0;
        $game->verticalplayer = 0;
        $game->currentturn = 0;
        $game->status = 'invited';
        $game->boardstatejson = null;
        $game->revealedcellsjson = null;
        $game->solvedwordsjson = '[]';
        $game->playerascore = 0;
        $game->playerbscore = 0;
        $game->lastmove = get_string('mp_lastmove_invitation_sent', 'crossduel');
        $game->lastplayer = $fromuserid;
        $game->lastmovetime = time();
        $game->timecreated = time();
        $game->timemodified = time();

        $DB->insert_record('crossduel_game', $game);
    }

    public static function accept_invitation(stdClass $game): void {
        global $DB;

        $game->status = 'active';
        $game->horizontalplayer = (int)$game->playera;
        $game->verticalplayer = (int)$game->playerb;
        $game->currentturn = (int)$game->playera;
        $game->solvedwordsjson = '[]';
        $game->lastmove = get_string('mp_lastmove_invitation_accepted', 'crossduel');
        $game->lastplayer = (int)$game->playerb;
        $game->lastmovetime = time();
        $game->timemodified = time();

        $DB->update_record('crossduel_game', $game);
    }

    public static function decline_invitation(stdClass $game): void {
        global $DB;

        $game->status = 'declined';
        $game->lastmove = get_string('mp_lastmove_invitation_declined', 'crossduel');
        $game->lastplayer = (int)$game->playerb;
        $game->lastmovetime = time();
        $game->timemodified = time();

        $DB->update_record('crossduel_game', $game);
    }

    public static function get_role_label(stdClass $game, int $userid): string {
        if ((int)$game->horizontalplayer === $userid) {
            return get_string('across', 'crossduel');
        }

        if ((int)$game->verticalplayer === $userid) {
            return get_string('down', 'crossduel');
        }

        return get_string('role_not_assigned', 'crossduel');
    }

    public static function get_solved_word_ids(stdClass $game): array {
        $decoded = [];
        $raw = isset($game->solvedwordsjson) ? (string)$game->solvedwordsjson : '[]';
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return $decoded;
        }

        foreach ($data as $wordid) {
            $decoded[(int)$wordid] = true;
        }

        return $decoded;
    }

    public static function save_solved_word_ids(stdClass $game, array $solvedwordids): void {
        global $DB;

        $ids = array_map('intval', array_keys($solvedwordids));
        sort($ids);

        $game->solvedwordsjson = json_encode(array_values($ids));
        $game->timemodified = time();

        $DB->update_record('crossduel_game', $game);
    }

    public static function get_user_direction(stdClass $game, int $userid): string {
        if ((int)$game->horizontalplayer === $userid) {
            return 'H';
        }

        if ((int)$game->verticalplayer === $userid) {
            return 'V';
        }

        return '';
    }

    public static function word_allowed(stdClass $row, string $userdirection): bool {
        return $userdirection !== '' && $row->direction === $userdirection;
    }

    public static function has_unsolved_direction_words(array $layoutrows, array $solvedwordids, string $direction): bool {
        foreach ($layoutrows as $row) {
            if ($row->direction !== $direction) {
                continue;
            }

            if (!isset($solvedwordids[(int)$row->wordid])) {
                return true;
            }
        }

        return false;
    }

    public static function get_next_turn(stdClass $game, array $layoutrows, array $solvedwordids, int $currentuserid): int {
        $playeradirection = ((int)$game->horizontalplayer === (int)$game->playera) ? 'H' : 'V';
        $playerbdirection = ((int)$game->horizontalplayer === (int)$game->playerb) ? 'H' : 'V';

        $playerahas = self::has_unsolved_direction_words($layoutrows, $solvedwordids, $playeradirection);
        $playerbhas = self::has_unsolved_direction_words($layoutrows, $solvedwordids, $playerbdirection);

        $otheruserid = ((int)$currentuserid === (int)$game->playera) ? (int)$game->playerb : (int)$game->playera;

        if ((int)$otheruserid === (int)$game->playera && $playerahas) {
            return (int)$game->playera;
        }

        if ((int)$otheruserid === (int)$game->playerb && $playerbhas) {
            return (int)$game->playerb;
        }

        if ((int)$currentuserid === (int)$game->playera && $playerahas) {
            return (int)$game->playera;
        }

        if ((int)$currentuserid === (int)$game->playerb && $playerbhas) {
            return (int)$game->playerb;
        }

        return (int)$game->currentturn;
    }

    public static function store_move(
        stdClass $game,
        array $layoutrows,
        int $userid,
        stdClass $targetrow,
        string $submittedanswer,
        bool $correct
    ): void {
        global $DB;

        $move = new stdClass();
        $move->gameid = (int)$game->id;
        $move->userid = $userid;
        $move->wordid = (int)$targetrow->wordid;
        $move->direction = $targetrow->direction;
        $move->submittedanswer = $submittedanswer;
        $move->correct = $correct ? 1 : 0;
        $move->pointsawarded = 0;
        $move->movesummary = $correct
            ? get_string('mp_move_correct', 'crossduel')
            : get_string('mp_move_incorrect', 'crossduel');
        $move->timecreated = time();

        $DB->insert_record('crossduel_move', $move);

        $solvedwordids = self::get_solved_word_ids($game);

        if ($correct) {
            $solvedwordids[(int)$targetrow->wordid] = true;
        }

        $game->lastmove = $correct
            ? get_string('mp_lastmove_correct_submitted', 'crossduel')
            : get_string('mp_lastmove_incorrect_submitted', 'crossduel');
        $game->lastplayer = $userid;
        $game->lastmovetime = time();
        $game->currentturn = self::get_next_turn($game, $layoutrows, $solvedwordids, $userid);

        self::save_solved_word_ids($game, $solvedwordids);
    }

    public static function push_explicit_grade(stdClass $crossduel, int $userid, float $rawgrade): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $item = [];
        $item['itemname'] = clean_param($crossduel->name, PARAM_NOTAGS);
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = isset($crossduel->grade) ? (float)$crossduel->grade : 100.0;
        $item['grademin'] = 0;

        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = $rawgrade;
        $grade->datesubmitted = time();
        $grade->dategraded = time();

        grade_update(
            'mod/crossduel',
            $crossduel->course,
            'mod',
            'crossduel',
            $crossduel->id,
            0,
            [$userid => $grade],
            $item
        );
    }

    public static function finalize_if_complete(stdClass $game, array $layoutrows): void {
        global $DB;

        $required = [];

        foreach ($layoutrows as $row) {
            $required[(int)$row->wordid] = true;
        }

        $solved = self::get_solved_word_ids($game);

        foreach ($required as $wordid => $unused) {
            if (!isset($solved[$wordid])) {
                return;
            }
        }

        if ($game->status !== 'completed') {
            $game->status = 'completed';
            $game->lastmove = get_string('mp_lastmove_completed', 'crossduel');
            $game->lastmovetime = time();
            $game->timemodified = time();

            $DB->update_record('crossduel_game', $game);
        }

        $crossduel = $DB->get_record('crossduel', ['id' => $game->crossduelid], '*', MUST_EXIST);
        $finalgrade = isset($crossduel->grade) ? (float)$crossduel->grade : 100.0;

        self::push_explicit_grade($crossduel, (int)$game->playera, $finalgrade);
        self::push_explicit_grade($crossduel, (int)$game->playerb, $finalgrade);
    }
}