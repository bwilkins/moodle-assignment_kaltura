<?php
/**
 * Kaltura Video assignment type for Moodle 2
 * Copyright (C) 2009 Petr Skoda  (http://skodak.org)
 * Copyright (C) 2011 Catalyst IT (http://www.catalyst.net.nz)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author     Brett Wilkins <brett@catalyst.net.nz>
 * @license    http://www.gnu.org/licenses/agpl.html GNU Affero GPL v3 or later
 */

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/mod/assignment/lib.php');
require_once($CFG->dirroot . '/local/kaltura/lib.php');

/**
 * Extend the base assignment class for assignments where you upload a single file
 *
 */
class assignment_kaltura extends assignment_base {

    var $filearea = 'submission';

    function assignment_kaltura($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'kaltura';
    }

    function view() {
        global $OUTPUT, $CFG, $USER, $PAGE;
        $PAGE->requires->js('/local/kaltura/js/kaltura-play.js');
        $PAGE->requires->css('/local/kaltura/styles.css');

        $edit  = optional_param('edit', 0, PARAM_BOOL);
        $saved = optional_param('saved', 0, PARAM_BOOL);

        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        require_capability('mod/assignment:view', $context);

        $submission = $this->get_submission($USER->id, false);

        //Guest can not submit nor edit an assignment (bug: 4604)
        if (!is_enrolled($this->context, $USER, 'mod/assignment:submit')) {
            $editable = false;
        } else {
            $editable = $this->isopen() && (!$submission || $this->assignment->resubmit || !$submission->timemarked);
        }
        $editmode = ($editable and $edit);

        if ($editmode) {
            $PAGE->requires->js('/local/kaltura/js/kaltura-edit.js');
            // prepare form and process submitted data
            $data = new stdClass();
            $data->id         = $this->cm->id;
            $data->edit       = 1;
            if ($submission) {
                $data->sid          = $submission->id;
                $data->kalturavideo = $submission->data1;
            } else {
                $data->sid          = NULL;
                $data->kalturavideo = '';
            }

            $mform = new mod_assignment_kaltura_edit_form(null, array($data));

            if ($mform->is_cancelled()) {
                redirect($PAGE->url);
            }

            if ($data = $mform->get_data()) {
                $submission = $this->get_submission($USER->id, true); //create the submission if needed & its id

                $submission = $this->update_submission($data);

                //TODO fix log actions - needs db upgrade
                add_to_log($this->course->id, 'assignment', 'upload', 'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                $this->email_teachers($submission);

                //redirect to get updated submission date and word count
                redirect(new moodle_url($PAGE->url, array('saved'=>1)));
            }
        }

        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

/// print header, etc. and display form if needed
        if ($editmode) {
            $this->view_header(get_string('editmysubmission', 'assignment'));
        } else {
            $this->view_header();
        }

        $this->view_intro();

        $this->view_dates();

        if ($saved) {
            echo $OUTPUT->notification(get_string('submissionsaved', 'assignment'), 'notifysuccess');
        }

        if (is_enrolled($this->context, $USER)) {
            if ($editmode) {
                echo $OUTPUT->box_start('generalbox', 'onlineenter');
                $mform->display();
            } else {
                echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter', 'kaltura');
                echo '<script type="text/javascript">window.kaltura = {entryid: "'.$submission->data1.'"};</script>';
                echo '<div class="kalturaPlayer" style="margin:auto;"></div>';
            }
            echo $OUTPUT->box_end();
            if (!$editmode && $editable) {
                if (!empty($submission)) {
                    $submitbutton = "editmysubmission";
                } else {
                    $submitbutton = "addsubmission";
                }
                if ($this->assignment->resubmit) {
                    echo "<div style='text-align:center'>";
                    echo $OUTPUT->single_button(new moodle_url('view.php', array('id'=>$this->cm->id, 'edit'=>'1')), get_string($submitbutton, 'assignment'));
                    echo "</div>";
                }
            }

        }

        $this->view_feedback();

        $this->view_footer();
    }

    /*
     * Display the assignment dates
     */
    function view_dates() {
        global $USER, $CFG, $OUTPUT;

        if (!$this->assignment->timeavailable && !$this->assignment->timedue) {
            return;
        }

        echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
        echo '<table>';
        if ($this->assignment->timeavailable) {
            echo '<tr><td class="c0">'.get_string('availabledate','assignment').':</td>';
            echo '    <td class="c1">'.userdate($this->assignment->timeavailable).'</td></tr>';
        }
        if ($this->assignment->timedue) {
            echo '<tr><td class="c0">'.get_string('duedate','assignment').':</td>';
            echo '    <td class="c1">'.userdate($this->assignment->timedue).'</td></tr>';
        }
        $submission = $this->get_submission($USER->id);
        if ($submission) {
            echo '<tr><td class="c0">'.get_string('lastedited').':</td>';
            echo '    <td class="c1">'.userdate($submission->timemodified).'</td></tr>';
        }
        echo '</table>';
        echo $OUTPUT->box_end();
    }

    function update_submission($data) {
        global $CFG, $USER, $DB;

        $submission = $this->get_submission($USER->id, true);

        $update = new stdClass();
        $update->id           = $submission->id;
        $update->data1        = $data->kalturavideo;
        $update->data2        = $data->videotype;
        $update->timemodified = time();

        $DB->update_record('assignment_submissions', $update);

        $submission = $this->get_submission($USER->id);
        $this->update_grade($submission);
        return $submission;
    }


    function print_student_answer($userid, $return=false){
        global $OUTPUT, $CFG;
        if (!$submission = $this->get_submission($userid)) {
            return '';
        }
        $url = new moodle_url('/mod/assignment/type/kaltura/view.php?id='.$this->cm->id.'&userid='.$userid);
        $action = new popup_action('click', $url, 'file'.$userid, array('height' => 450, 'width' => 450));
        $popup = $OUTPUT->action_link($url, get_string('viewsubmission', 'assignment_kaltura'), $action, array('title'=>get_string('submission', 'assignment')));

        return '<div>'.$popup.'</div>';
    }

    function print_user_files($userid, $return=false) {
        global $OUTPUT, $CFG, $PAGE;

        if (!$submission = $this->get_submission($userid)) {
            return '';
        }
        $PAGE->requires->js('/local/kaltura/js/kaltura-play.js');
        $PAGE->requires->css('/local/kaltura/styles.css');

        $output = kalturaGlobals_js(array('entryid' => $submission->data1));
        $output .= '<div class="kalturaPlayer"></div>';

        return $output;
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE, $PAGE;
        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'resubmit', get_string('allowresubmit', 'assignment'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowresubmit', 'assignment');
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'assignment'), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'assignment');
        $mform->setDefault('emailteachers', 0);
    }

    function extend_settings_navigation($node) {
        global $PAGE, $CFG, $USER;

        // get users submission if there is one
        $submission = $this->get_submission();
        if (is_enrolled($PAGE->cm->context, $USER, 'mod/assignment:submit')) {
            //editing is practically the same as resubmitting in this case.
            $editable = $this->isopen() && (!$submission || !$submission->timemarked) && $this->assignment->resubmit;
        } else {
            $editable = false;
        }

        // If the user has submitted something add a bit more stuff
        if ($submission) {
            // Add a view link to the settings nav
            $link = new moodle_url('/mod/assignment/view.php', array('id'=>$PAGE->cm->id));
            $node->add(get_string('viewmysubmission', 'assignment'), $link, navigation_node::TYPE_SETTING);

            if (!empty($submission->timemodified)) {
                $submittednode = $node->add(get_string('submitted', 'assignment') . ' ' . userdate($submission->timemodified));
                $submittednode->text = preg_replace('#([^,])\s#', '$1&nbsp;', $submittednode->text);
                $submittednode->add_class('note');
                if ($submission->timemodified <= $this->assignment->timedue || empty($this->assignment->timedue)) {
                    $submittednode->add_class('early');
                } else {
                    $submittednode->add_class('late');
                }
            }
        }

        if (!$submission || $editable) {
            // If this assignment is editable once submitted add an edit link to the settings nav
            $link = new moodle_url('/mod/assignment/view.php', array('id'=>$PAGE->cm->id, 'edit'=>1, 'sesskey'=>sesskey()));
            $node->add(get_string('editmysubmission', 'assignment'), $link, navigation_node::TYPE_SETTING);
        }
    }
}

class mod_assignment_kaltura_edit_form extends moodleform {
    function definition() {
        $id = optional_param('id', 0, PARAM_INT);
        $mform = $this->_form;

        list($data, $editoroptions) = $this->_customdata;


        $mform->addElement('html', kalturaGlobals_js(array('cmid' => $id)));
        // visible elements
        $mform->addElement('html','<div class="kalturaPlayerEdit"></div>');
        $mform->addElement('submit', 'replacevideo', get_string('replacevideo', 'kalturavideo'));


        // hidden params
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'kalturavideo');
        $mform->setType('kalturavideo', PARAM_TEXT);

        // buttons
        $this->add_action_buttons();

        $this->set_data($data);
    }
}


