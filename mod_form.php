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
 * Activity settings form for mod_crossduel.
 *
 * @package    mod_crossduel
 * @copyright  Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form for Cross Duel.
 */
class mod_crossduel_mod_form extends moodleform_mod {

    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'crossduelsettings', get_string('crossduelsettings', 'crossduel'));

        $instructions = implode("\n", [
            get_string('instructions_line1', 'crossduel'),
            get_string('instructions_line2', 'crossduel'),
            '',
            get_string('instructions_examples', 'crossduel'),
            get_string('instructions_example1', 'crossduel'),
            get_string('instructions_example2', 'crossduel'),
            get_string('instructions_example3', 'crossduel'),
            '',
            get_string('instructions_rules', 'crossduel'),
            get_string('instructions_rule1', 'crossduel'),
            get_string('instructions_rule2', 'crossduel'),
            get_string('instructions_rule3', 'crossduel'),
            get_string('instructions_rule4', 'crossduel'),
            get_string('instructions_rule5', 'crossduel'),
            get_string('instructions_rule6', 'crossduel'),
        ]);

        $mform->addElement(
            'static',
            'crossduelinstructions',
            get_string('crossduelinstructions', 'crossduel'),
            html_writer::div(nl2br(s($instructions)), 'crossduel-form-instructions')
        );

        $mform->addElement(
            'textarea',
            'wordlist',
            get_string('wordlist', 'crossduel'),
            ['rows' => 16, 'cols' => 80]
        );
        $mform->setType('wordlist', PARAM_RAW);

        $mform->addElement(
            'text',
            'revealpercent',
            get_string('revealpercent', 'crossduel'),
            ['size' => '6']
        );
        $mform->setType('revealpercent', PARAM_FLOAT);
        $mform->setDefault('revealpercent', 10);

        $mform->addElement(
            'static',
            'revealpercenthelp',
            '',
            get_string('revealpercenthelptext', 'crossduel')
        );

        $mform->addElement(
            'text',
            'passpercentage',
            get_string('passpercentage', 'crossduel'),
            ['size' => '6']
        );
        $mform->setType('passpercentage', PARAM_FLOAT);
        $mform->setDefault('passpercentage', 60);

        $mform->addElement(
            'static',
            'passpercentagehelp',
            '',
            get_string('passpercentagehelptext', 'crossduel')
        );

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate submitted form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $rawtext = trim((string)($data['wordlist'] ?? ''));

        if ($rawtext === '') {
            $errors['wordlist'] = get_string('nowords', 'crossduel');
        } else {
            $lines = preg_split('/\r\n|\r|\n/', $rawtext);
            $validcount = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (substr_count($line, '|') !== 1) {
                    $errors['wordlist'] = get_string('invalidwordformat', 'crossduel');
                    break;
                }

                list($word, $clue) = array_map('trim', explode('|', $line, 2));

                if ($word === '' || $clue === '') {
                    $errors['wordlist'] = get_string('invalidwordformat', 'crossduel');
                    break;
                }

                $validcount++;
            }

            if (!isset($errors['wordlist'])) {
                if ($validcount === 0) {
                    $errors['wordlist'] = get_string('nowords', 'crossduel');
                } else if ($validcount > 50) {
                    $errors['wordlist'] = get_string('toomanywords', 'crossduel');
                }
            }
        }

        if (!is_numeric($data['revealpercent']) || $data['revealpercent'] < 5 || $data['revealpercent'] > 50) {
            $errors['revealpercent'] = get_string('revealpercentvalidation', 'crossduel');
        }

        if (!is_numeric($data['passpercentage']) || $data['passpercentage'] < 0 || $data['passpercentage'] > 100) {
            $errors['passpercentage'] = get_string('passpercentagevalidation', 'crossduel');
        }

        return $errors;
    }
}
