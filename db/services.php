<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * External service definitions for mod_crossduel.
 *
 * @package    mod_crossduel
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_crossduel_get_lobby_state' => [
        'classname'   => 'mod_crossduel\external\get_lobby_state',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Return multiplayer lobby polling state',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> 'mod/crossduel:play',
    ],

    'mod_crossduel_get_game_state' => [
        'classname'   => 'mod_crossduel\external\get_game_state',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Return active multiplayer game polling state',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> 'mod/crossduel:play',
    ],
];