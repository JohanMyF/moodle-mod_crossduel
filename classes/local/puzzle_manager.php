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
// This file is part of Moodle - http://moodle.org/

/**
 * Puzzle layout helper logic for mod_crossduel.
 *
 * @package    mod_crossduel
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crossduel\local;

defined('MOODLE_INTERNAL') || die();

use core_text;
use stdClass;

class puzzle_manager {

    public static function get_approved_layout_rows(int $crossduelid): array {
        global $DB;

        $sql = "
            SELECT
                ls.id AS layoutslotid,
                ls.crossduelid,
                ls.wordid,
                ls.direction,
                ls.startrow,
                ls.startcol,
                ls.cluenumber,
                ls.placementorder,
                ls.isactive,
                w.rawword,
                w.normalizedword,
                w.cluetext,
                w.wordlength,
                w.sortorder
            FROM {crossduel_layoutslot} ls
            JOIN {crossduel_word} w
              ON w.id = ls.wordid
            WHERE ls.crossduelid = :crossduelid
              AND ls.isactive = 1
            ORDER BY ls.cluenumber ASC, ls.placementorder ASC
        ";

        return $DB->get_records_sql($sql, ['crossduelid' => $crossduelid]);
    }

    public static function build_grid(array $layoutrows): array {
        $grid = [];

        foreach ($layoutrows as $row) {
            $word = $row->normalizedword;
            $length = core_text::strlen($word);

            for ($i = 0; $i < $length; $i++) {
                $letter = core_text::substr($word, $i, 1);

                if ($row->direction === 'H') {
                    $gridrow = (int)$row->startrow;
                    $gridcol = (int)$row->startcol + $i;
                } else {
                    $gridrow = (int)$row->startrow + $i;
                    $gridcol = (int)$row->startcol;
                }

                if (!isset($grid[$gridrow])) {
                    $grid[$gridrow] = [];
                }

                $grid[$gridrow][$gridcol] = $letter;
            }
        }

        return $grid;
    }

    public static function get_bounds(array $layoutrows): array {
        $bounds = [
            'minrow' => 0,
            'maxrow' => 0,
            'mincol' => 0,
            'maxcol' => 0,
        ];

        $first = true;

        foreach ($layoutrows as $row) {
            $length = (int)$row->wordlength;

            if ($row->direction === 'H') {
                $minrow = (int)$row->startrow;
                $maxrow = (int)$row->startrow;
                $mincol = (int)$row->startcol;
                $maxcol = (int)$row->startcol + $length - 1;
            } else {
                $minrow = (int)$row->startrow;
                $maxrow = (int)$row->startrow + $length - 1;
                $mincol = (int)$row->startcol;
                $maxcol = (int)$row->startcol;
            }

            if ($first) {
                $bounds['minrow'] = $minrow;
                $bounds['maxrow'] = $maxrow;
                $bounds['mincol'] = $mincol;
                $bounds['maxcol'] = $maxcol;
                $first = false;
            } else {
                $bounds['minrow'] = min($bounds['minrow'], $minrow);
                $bounds['maxrow'] = max($bounds['maxrow'], $maxrow);
                $bounds['mincol'] = min($bounds['mincol'], $mincol);
                $bounds['maxcol'] = max($bounds['maxcol'], $maxcol);
            }
        }

        return $bounds;
    }

    public static function get_grid_cell(array $grid, int $row, int $col): ?string {
        if (!isset($grid[$row])) {
            return null;
        }

        if (!array_key_exists($col, $grid[$row])) {
            return null;
        }

        return $grid[$row][$col];
    }

    public static function split_clues(array $layoutrows): array {
        $across = [];
        $down = [];

        foreach ($layoutrows as $row) {
            $item = [
                'wordid' => (int)$row->wordid,
                'cluenumber' => (int)$row->cluenumber,
                'clue' => $row->cluetext,
                'word' => $row->rawword,
                'normalized' => $row->normalizedword,
                'length' => (int)$row->wordlength,
                'direction' => $row->direction,
            ];

            if ($row->direction === 'H') {
                $across[] = $item;
            } else {
                $down[] = $item;
            }
        }

        return [
            'across' => $across,
            'down' => $down,
        ];
    }

    public static function get_startcell_numbers(array $layoutrows): array {
        $numbers = [];

        foreach ($layoutrows as $row) {
            $key = (int)$row->startrow . ':' . (int)$row->startcol;

            if (!isset($numbers[$key])) {
                $numbers[$key] = (int)$row->cluenumber;
            } else {
                $numbers[$key] = min($numbers[$key], (int)$row->cluenumber);
            }
        }

        return $numbers;
    }

    public static function get_word_cells(stdClass $row): array {
        $cells = [];
        $word = $row->normalizedword;
        $length = core_text::strlen($word);

        for ($i = 0; $i < $length; $i++) {
            $letter = core_text::substr($word, $i, 1);

            if ($row->direction === 'H') {
                $gridrow = (int)$row->startrow;
                $gridcol = (int)$row->startcol + $i;
            } else {
                $gridrow = (int)$row->startrow + $i;
                $gridcol = (int)$row->startcol;
            }

            $cells[] = [
                'row' => $gridrow,
                'col' => $gridcol,
                'letter' => $letter,
            ];
        }

        return $cells;
    }

    public static function get_revealed_cells(array $layoutrows, float $revealpercent, array $solvedwordids): array {
        $revealed = [];
        $allcells = [];

        foreach ($layoutrows as $row) {
            $wordcells = self::get_word_cells($row);

            if (!empty($wordcells)) {
                $firstcell = $wordcells[0];
                $firstkey = $firstcell['row'] . ':' . $firstcell['col'];
                $revealed[$firstkey] = true;
            }

            foreach ($wordcells as $cell) {
                $allcells[] = $cell;
            }
        }

        $uniqueoccupied = [];

        foreach ($allcells as $cell) {
            $key = $cell['row'] . ':' . $cell['col'];
            $uniqueoccupied[$key] = true;
        }

        $totaloccupied = count($uniqueoccupied);

        if ($totaloccupied > 0) {
            $targetcount = (int)ceil(($revealpercent / 100) * $totaloccupied);
            $targetcount = max(1, $targetcount);

            if (count($revealed) < $targetcount) {
                foreach ($allcells as $cell) {
                    $key = $cell['row'] . ':' . $cell['col'];

                    if (!isset($revealed[$key])) {
                        $revealed[$key] = true;
                    }

                    if (count($revealed) >= $targetcount) {
                        break;
                    }
                }
            }
        }

        foreach ($layoutrows as $row) {
            if (!isset($solvedwordids[(int)$row->wordid])) {
                continue;
            }

            $wordcells = self::get_word_cells($row);

            foreach ($wordcells as $cell) {
                $key = $cell['row'] . ':' . $cell['col'];
                $revealed[$key] = true;
            }
        }

        return $revealed;
    }

    public static function build_matrix(array $grid, array $bounds, array $revealedcells, array $startcellnumbers = []): array {
    $rows = [];

    if (empty($grid)) {
        return $rows;
    }

    for ($row = $bounds['minrow']; $row <= $bounds['maxrow']; $row++) {
        $rowcells = [];

        for ($col = $bounds['mincol']; $col <= $bounds['maxcol']; $col++) {
            $cell = self::get_grid_cell($grid, $row, $col);
            $key = $row . ':' . $col;
            $isempty = ($cell === null);
            $isrevealed = isset($revealedcells[$key]);
            $hasnumber = isset($startcellnumbers[$key]);

            $rowcells[] = [
                'row' => $row,
                'col' => $col,
                'letter' => $isempty ? '' : $cell,
                'displayletter' => (!$isempty && $isrevealed) ? $cell : '',
                'revealed' => $isrevealed,
                'isrevealed' => $isrevealed,
                'isempty' => $isempty,
                'isfilled' => !$isempty,
                'hasnumber' => $hasnumber,
                'number' => $hasnumber ? (string)$startcellnumbers[$key] : '',
            ];
        }

        $rows[] = [
            'cells' => $rowcells,
        ];
    }

    return $rows;
}
}