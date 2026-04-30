<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External service returning current multiplayer game state.
 *
 * @package    mod_crossduel
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crossduel\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_crossduel\local\multiplayer_manager;

class get_game_state extends external_api {

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

        $game = multiplayer_manager::get_user_current_game((int)$crossduel->id, (int)$USER->id);

        if (!$game) {
            return [
                'hasgame' => 0,
                'gameid' => 0,
                'status' => '',
                'playera' => 0,
                'playerb' => 0,
                'horizontalplayer' => 0,
                'verticalplayer' => 0,
                'currentturn' => 0,
                'timemodified' => 0,
                'lastmove' => '',
                'lastplayer' => 0,
                'lastmovetime' => 0,
            ];
        }

        return [
            'hasgame' => 1,
            'gameid' => (int)$game->id,
            'status' => (string)$game->status,
            'playera' => (int)$game->playera,
            'playerb' => (int)$game->playerb,
            'horizontalplayer' => (int)$game->horizontalplayer,
            'verticalplayer' => (int)$game->verticalplayer,
            'currentturn' => (int)$game->currentturn,
            'timemodified' => (int)$game->timemodified,
            'lastmove' => (string)$game->lastmove,
            'lastplayer' => (int)$game->lastplayer,
            'lastmovetime' => (int)$game->lastmovetime,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'hasgame' => new external_value(PARAM_INT, 'Whether the current user has a multiplayer game'),
            'gameid' => new external_value(PARAM_INT, 'Game id'),
            'status' => new external_value(PARAM_TEXT, 'Game status'),
            'playera' => new external_value(PARAM_INT, 'Player A user id'),
            'playerb' => new external_value(PARAM_INT, 'Player B user id'),
            'horizontalplayer' => new external_value(PARAM_INT, 'Horizontal player user id'),
            'verticalplayer' => new external_value(PARAM_INT, 'Vertical player user id'),
            'currentturn' => new external_value(PARAM_INT, 'Current turn user id'),
            'timemodified' => new external_value(PARAM_INT, 'Modified time'),
            'lastmove' => new external_value(PARAM_TEXT, 'Last move summary'),
            'lastplayer' => new external_value(PARAM_INT, 'Last player user id'),
            'lastmovetime' => new external_value(PARAM_INT, 'Last move time'),
        ]);
    }
}