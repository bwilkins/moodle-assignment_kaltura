<?php
/**
 * Kaltura Video assignment type for Moodle 2
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
 * @package    mod
 * @subpackage kalturavideo assignment type
 * @author     Brett Wilkins <brett@catalyst.net.nz>
 * @license    http://www.gnu.org/licenses/agpl.html GNU Affero GPL v3 or later
 */

require_once('../../../../config.php');

$id     = required_param('id', 0, PARAM_INT);
$userid = required_param('userid', 0, PARAM_INT);

$PAGE->requires->js('/local/kaltura/js/kaltura-play.js');

//get course module/context from cmid
$cm = get_coursemodule_from_id('assignment', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);

$user = $DB->get_record('user',array('id'=>$userid), '*', MUST_EXIST);

if (($USER->id != $user->id) && !has_capability('mod/assignment:grade', $context)) {
    print_error('cannotviewassignment', 'assignment');
}

$PAGE->set_pagelayout('popup');
$PAGE->set_title(fullname($user, true).': '.$cm->name);
$url = new moodle_url('/mod/assignment/type/kaltura/view.php', array('id'=>$id, 'userid'=>$userid));
$PAGE->set_url($url);
//$PAGE->navbar->add(get_string('submittedcontent','assignment_kaltura'),

//get submission from cm
$submission = $DB->get_record('assignment_submissions', array('assignment'=>$cm->instance, 'userid'=>$userid), '*', MUST_EXIST);

if (!empty($submission->data1)) {
    //display kaltura player
    echo $OUTPUT->header();
    echo $OUTPUT->box_start();
    echo '<script type="text/javascript">window.kaltura = {entryid: "'.$submission->data1.'"}</script>';
    echo '<div class="kalturaPlayer"></div>';
    echo $OUTPUT->box_end();
    echo $OUTPUT->close_window_button();
    echo $OUTPUT->footer();
}
else {
    print_error('emptysubmission','assignment');
}
