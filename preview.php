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
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id('crossduel', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$crossduel = $DB->get_record('crossduel', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crossduel:addinstance', $context);

$PAGE->set_url('/mod/crossduel/preview.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('previewtitle', 'crossduel', format_string($crossduel->name)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

/**
 * Parse the stored word list into structured rows.
 *
 * Each non-blank line must be:
 *   word|clue
 *
 * @param string $rawtext Raw word list from the activity settings
 * @return array Parsed entries
 */
function crossduel_preview_parse_wordlist(string $rawtext): array {
    $entries = [];

    $lines = preg_split('/\r\n|\r|\n/', $rawtext);

    foreach ($lines as $index => $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (substr_count($line, '|') !== 1) {
            continue;
        }

        list($word, $clue) = array_map('trim', explode('|', $line, 2));

        if ($word === '' || $clue === '') {
            continue;
        }

        $normalized = core_text::strtoupper($word);
        $normalized = preg_replace('/[^[:alnum:]]/u', '', $normalized);

        $entries[] = [
            'line' => $index + 1,
            'word' => $word,
            'normalized' => $normalized,
            'clue' => $clue,
            'length' => core_text::strlen($normalized),
        ];
    }

    return $entries;
}

/**
 * Build a lookup of structured stored words keyed by normalized word + sort order.
 *
 * This helps us match the preview-generated placed words back to actual
 * crossduel_word table rows.
 *
 * @param int $crossduelid
 * @return array
 */
function crossduel_preview_get_stored_word_lookup(int $crossduelid): array {
    global $DB;

    $records = $DB->get_records(
        'crossduel_word',
        ['crossduelid' => $crossduelid],
        'sortorder ASC'
    );

    $lookup = [];

    foreach ($records as $record) {
        $key = $record->normalizedword . '|' . $record->sortorder;
        $lookup[$key] = $record;
    }

    return $lookup;
}

/**
 * Save the approved placed words into crossduel_layoutslot and mark the
 * activity layout as approved.
 *
 * Matching rule:
 * - We match by normalized word + original line number.
 * - In this plugin, preview line number corresponds to stored sortorder.
 *
 * @param stdClass $crossduel
 * @param array $layoutresult
 * @return void
 */
function crossduel_preview_save_approved_layout(stdClass $crossduel, array $layoutresult): void {
    global $DB;

    $lookup = crossduel_preview_get_stored_word_lookup((int)$crossduel->id);

    $transaction = $DB->start_delegated_transaction();

    // Remove any previous stored layout for this activity.
    $DB->delete_records('crossduel_layoutslot', ['crossduelid' => $crossduel->id]);

    $cluenumber = 1;
    $placementorder = 1;

    foreach ($layoutresult['placed'] as $placed) {
        $key = $placed['normalized'] . '|' . $placed['line'];

        if (!isset($lookup[$key])) {
            // Fail safely if we cannot match a placed word back to its source row.
            throw new moodle_exception(
                'Could not match a placed word back to its stored crossduel_word record: ' . $placed['normalized']
            );
        }

        $storedword = $lookup[$key];

        $record = new stdClass();
        $record->crossduelid = $crossduel->id;
        $record->wordid = $storedword->id;
        $record->direction = $placed['direction'];
        $record->startrow = $placed['startrow'];
        $record->startcol = $placed['startcol'];
        $record->cluenumber = $cluenumber;
        $record->placementorder = $placementorder;
        $record->isactive = 1;

        $DB->insert_record('crossduel_layoutslot', $record);

        $cluenumber++;
        $placementorder++;
    }

    $crossduel->layoutapproved = 1;
    $crossduel->timemodified = time();
    $DB->update_record('crossduel', $crossduel);

    $transaction->allow_commit();
}

$entries = crossduel_preview_parse_wordlist((string)$crossduel->wordlist);
$layoutresult = crossduel_generate_draft_layout($entries);
$matrix = crossduel_build_render_matrix($layoutresult);

$approvalmessage = '';
$approvalsuccess = false;

/*
 * -------------------------------------------------------------
 * Handle teacher approval
 * -------------------------------------------------------------
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('approvelayout', '', PARAM_TEXT) !== '') {
    require_sesskey();

    if (empty($layoutresult['placed'])) {
        $approvalmessage = get_string('nodraft', 'crossduel');
    } else {
        try {
            crossduel_preview_save_approved_layout($crossduel, $layoutresult);

            // Refresh the main activity record after saving approval.
            $crossduel = $DB->get_record('crossduel', ['id' => $crossduel->id], '*', MUST_EXIST);

            $approvalsuccess = true;
            $approvalmessage = get_string('draftapproved', 'crossduel');
        } catch (Exception $e) {
            $approvalmessage = $e->getMessage();
        }
    }
}

/*
 * -------------------------------------------------------------
 * Simple preview styles
 * -------------------------------------------------------------
 */
$PAGE->requires->css('/mod/crossduel/styles.css');

echo $OUTPUT->header();
echo html_writer::tag('style', $styles);
echo $OUTPUT->heading(get_string('previewheading', 'crossduel'));

if ($approvalmessage !== '') {
    if ($approvalsuccess) {
        echo $OUTPUT->notification($approvalmessage, 'success');
    } else {
        echo $OUTPUT->notification($approvalmessage, 'warning');
    }
}

/*
 * -------------------------------------------------------------
 * Introductory explanation
 * -------------------------------------------------------------
 */
echo $OUTPUT->box(
    html_writer::tag('p', get_string('previewintro1', 'crossduel')) .
    html_writer::tag('p', get_string('previewintro2', 'crossduel')),
    'generalbox'
);

/*
 * -------------------------------------------------------------
 * Activity summary
 * -------------------------------------------------------------
 */
$summaryitems = [];
$summaryitems[] = html_writer::tag('li', get_string('activityname_label', 'crossduel', s($crossduel->name)));
$summaryitems[] = html_writer::tag('li', get_string('entriesfound', 'crossduel', count($entries)));
$summaryitems[] = html_writer::tag('li', get_string('placedcount', 'crossduel', count($layoutresult['placed'])));
$summaryitems[] = html_writer::tag('li', get_string('skippedcount', 'crossduel', count($layoutresult['skipped'])));
$summaryitems[] = html_writer::tag('li', get_string('revealpercent_label', 'crossduel', s($crossduel->revealpercent)));
$summaryitems[] = html_writer::tag('li', get_string('passpercent_label', 'crossduel', s($crossduel->passpercentage)));
$summaryitems[] = html_writer::tag(
    'li',
    get_string(
        'layoutapproved_label',
        'crossduel',
        $crossduel->layoutapproved ? get_string('yes', 'crossduel') : get_string('no', 'crossduel')
    )
);

echo $OUTPUT->box(
    html_writer::tag('h3', get_string('activitysummary', 'crossduel')) .
    html_writer::tag('ul', implode('', $summaryitems)),
    'generalbox'
);

/*
 * -------------------------------------------------------------
 * Warnings
 * -------------------------------------------------------------
 */
if (!empty($layoutresult['warnings'])) {
    foreach ($layoutresult['warnings'] as $warning) {
        echo $OUTPUT->notification($warning, 'warning');
    }
}

/*
 * -------------------------------------------------------------
 * Placed words
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-preview-card');
echo $OUTPUT->heading(get_string('placedwords', 'crossduel'), 3);

if (empty($layoutresult['placed'])) {
    echo html_writer::tag('p', get_string('noplaced', 'crossduel'), ['class' => 'crossduel-preview-note']);
} else {
    $table = new html_table();
    $table->head = [
        'Line',
        'Word',
        'Normalized',
        'Direction',
        'Start row',
        'Start col',
        'Length',
        'Clue',
    ];
    $table->attributes['class'] = 'generaltable';

    $table->data = [];

    foreach ($layoutresult['placed'] as $placed) {
        $directionlabel = ($placed['direction'] === 'H')
            ? get_string('direction_horizontal', 'crossduel')
            : get_string('direction_vertical', 'crossduel');

        $table->data[] = [
            $placed['line'],
            s($placed['word']),
            s($placed['normalized']),
            html_writer::tag('span', $directionlabel, ['class' => 'crossduel-direction-pill']),
            $placed['startrow'],
            $placed['startcol'],
            $placed['length'],
            s($placed['clue']),
        ];
    }

    echo html_writer::table($table);
}
echo html_writer::end_div();

/*
 * -------------------------------------------------------------
 * Skipped words
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-preview-card');
echo $OUTPUT->heading(get_string('skippedwords', 'crossduel'), 3);

if (empty($layoutresult['skipped'])) {
    echo html_writer::tag('p', get_string('noskipped', 'crossduel'), ['class' => 'crossduel-preview-note']);
} else {
    $table = new html_table();
    $table->head = [
        'Line',
        'Word',
        'Normalized',
        'Length',
        'Clue',
        'Status',
    ];
    $table->attributes['class'] = 'generaltable';

    $table->data = [];

    foreach ($layoutresult['skipped'] as $skipped) {
        $table->data[] = [
            $skipped['line'],
            s($skipped['word']),
            s($skipped['normalized']),
            $skipped['length'],
            s($skipped['clue']),
            html_writer::tag('span', get_string('skipped', 'crossduel'), ['class' => 'crossduel-skipped-pill']),
        ];
    }

    echo html_writer::table($table);
}
echo html_writer::end_div();

/*
 * -------------------------------------------------------------
 * Draft grid
 * -------------------------------------------------------------
 */
echo html_writer::start_div('crossduel-preview-card');
echo $OUTPUT->heading(get_string('draftgrid', 'crossduel'), 3);

if (empty($matrix['rows'])) {
    echo html_writer::tag('p', get_string('nogrid', 'crossduel'), ['class' => 'crossduel-preview-note']);
} else {
    echo html_writer::tag('p', get_string('gridnote', 'crossduel'), ['class' => 'crossduel-preview-note']);

    echo html_writer::start_tag('table', ['class' => 'crossduel-preview-grid']);

    foreach ($matrix['rows'] as $rowcells) {
        echo html_writer::start_tag('tr');

        foreach ($rowcells as $cell) {
            if ($cell === '') {
                echo html_writer::tag('td', '', ['class' => 'crossduel-empty']);
            } else {
                echo html_writer::tag('td', s($cell), ['class' => 'crossduel-filled']);
            }
        }

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('table');
}
echo html_writer::end_div();

/*
 * -------------------------------------------------------------
 * Action area
 * -------------------------------------------------------------
 */
$viewurl = new moodle_url('/mod/crossduel/view.php', ['id' => $cm->id]);
$editurl = new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]);

echo $OUTPUT->box_start('generalbox');
echo html_writer::tag('h3', get_string('nextstep', 'crossduel'));
echo html_writer::tag('p', get_string('nextstepdesc', 'crossduel'));

echo html_writer::start_div('crossduel-action-row');

echo html_writer::link($editurl, get_string('editsettings', 'crossduel'), ['class' => 'btn btn-secondary']);
echo html_writer::link($viewurl, get_string('backtoactivity', 'crossduel'), ['class' => 'btn btn-secondary']);

if (!empty($layoutresult['placed'])) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'style' => 'display:inline;'
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'sesskey',
        'value' => sesskey(),
    ]);

    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'approvelayout',
        'value' => get_string('approve_layout', 'crossduel'),
        'class' => 'btn btn-primary',
    ]);

    echo html_writer::end_tag('form');
}

echo html_writer::end_div();
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
