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


require('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = get_course($id);
require_login($course);

$PAGE->set_url('/mod/crossduel/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'crossduel'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('modulenameplural', 'crossduel'));

if (!$crossduels = get_all_instances_in_course('crossduel', $course)) {
    notice(get_string('nonewmodules', 'crossduel'), new moodle_url('/course/view.php', ['id' => $course->id]));
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$table->head = [
    get_string('name'),
];

$table->data = [];

foreach ($crossduels as $crossduel) {
    $link = html_writer::link(
        new moodle_url('/mod/crossduel/view.php', ['id' => $crossduel->coursemodule]),
        format_string($crossduel->name)
    );

    $table->data[] = [$link];
}

echo html_writer::table($table);

echo $OUTPUT->footer();