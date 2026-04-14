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
 * [Short description of what this file does]
 *
 * @package    mod_crossduel
 * @author     Johan Venter <johan@myfutureway.co.za>
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    /*
     * -------------------------------------------------------------
     * Standard capability: add a new Cross Duel activity instance
     * -------------------------------------------------------------
     *
     * Why this exists:
     * Teachers or other editing users need permission to add this
     * activity to a course.
     *
     * Why the riskbitmask is RISK_XSS:
     * Activity creation usually involves user-authored content such as
     * names, instructions, and teacher-entered text. Moodle commonly
     * marks addinstance with this risk type.
     */
    'mod/crossduel:addinstance' => [
        'riskbitmask' => RISK_XSS,

        'captype' => 'write',

        'contextlevel' => CONTEXT_COURSE,

        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],

        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    /*
     * -------------------------------------------------------------
     * Custom capability: play/view the Cross Duel activity
     * -------------------------------------------------------------
     *
     * Why this exists:
     * We want a clear plugin-specific capability we can check in
     * view.php later.
     *
     * Students should normally have this permission, as should teachers
     * and managers.
     */
    'mod/crossduel:play' => [
        'captype' => 'read',

        'contextlevel' => CONTEXT_MODULE,

        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];