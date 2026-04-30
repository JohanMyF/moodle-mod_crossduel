<?php
namespace mod_crossduel\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use external_warnings;
use context_module;

class get_lobby_state extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    public static function execute(int $cmid): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid]
        );

        $cm = get_coursemodule_from_id('crossduel', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/crossduel:play', $context);

        $crossduel = $DB->get_record('crossduel', ['id' => $cm->instance], '*', MUST_EXIST);

        $latestgame = $DB->get_records_select(
            'crossduel_game',
            'crossduelid = ? AND (playera = ? OR playerb = ?)',
            [$crossduel->id, $USER->id, $USER->id],
            'id DESC',
            '*',
            0,
            1
        );

        $latestgame = $latestgame ? reset($latestgame) : false;

        $pendinginvites = $DB->count_records('crossduel_game', [
            'crossduelid' => $crossduel->id,
            'playerb' => $USER->id,
            'status' => 'invited',
        ]);

        return [
            'pendinginvitecount' => $pendinginvites,
            'latestgameid' => $latestgame ? (int)$latestgame->id : 0,
            'latestgamestatus' => $latestgame ? (string)$latestgame->status : '',
            'latesttimemodified' => $latestgame ? (int)$latestgame->timemodified : 0,
            'latestlastmovetime' => $latestgame ? (int)$latestgame->lastmovetime : 0,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pendinginvitecount' => new external_value(PARAM_INT, 'Pending invites'),
            'latestgameid' => new external_value(PARAM_INT, 'Latest game id'),
            'latestgamestatus' => new external_value(PARAM_TEXT, 'Game status'),
            'latesttimemodified' => new external_value(PARAM_INT, 'Modified time'),
            'latestlastmovetime' => new external_value(PARAM_INT, 'Last move time'),
        ]);
    }
}