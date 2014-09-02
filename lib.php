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
 * repository_recordaudio class
 *
 * @since 2.0
 * @package    repository
 * @subpackage recordaudio
 * @copyright  2012 Paul Nicholls
 * @author     Paul Nicholls <paul.nicholls@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class repository_recordaudio extends repository {

    /**
     * RecordAudio plugin constructor
     * @param int $repositoryid
     * @param object $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        parent::__construct($repositoryid, $context, $options);
    }

    private function get_recorder() {
        global $CFG;
        $recorder = "";
        $url=$CFG->wwwroot.'/repository/recordaudio/assets/recorder.swf?gateway=form';
        // Justin: Here there was some code to disable the recordaudio_filename field, but since it was a hidden field, it messed it up somehow. I removed that code and the filename got passed through ok
        $callback = urlencode("(function(a, b){d=document;d.g=d.getElementById;fn=d.g('recordaudio_filename');fn.value=a;fd=d.g('recordaudio_filedata');fd.value=b;f=fn;while(f.tagName!='FORM')f=f.parentNode;uf=d.g(f.repo_upload_file.id);uf2=d.createElement('input');uf2.type='hidden';uf2.name='repo_upload_file';uf2.value='bogus.mp3';uf.parentNode.replaceChild(uf2,uf);while(f.tagName!='DIV')f=f.nextSibling;f.getElementsByTagName('button')[0].click();})");
        $flashvars="&callback={$callback}&filename=new_recording";

        $recorder = '<div class="repository_recordaudio_wrapper">
                <input type="hidden"  name="recordaudio_filename" id="recordaudio_filename" />
                <textarea name="recordaudio_filedata" id="recordaudio_filedata"></textarea>
                <div>
                    <object type="application/x-shockwave-flash" data="'.$url.$flashvars.'" width="215" height="138">
                        <param name="movie" value="'.$url.$flashvars.'" />
                        <param name="wmode" value="transparent" />
                        <!--[if !IE]>-->
                        <object type="application/x-shockwave-flash" data="'.$url.$flashvars.'" width="215" height="138">
                        <!--<![endif]-->
                        <div>
                                <p><a href="http://www.adobe.com/go/getflashplayer"><img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" /></a></p>
                        </div>
                        <!--[if !IE]>-->
                        </object>
                        <!--<![endif]-->
                    </object>
                </div>
            </div>';

        return $recorder;
    }

    public function get_upload_template() {
        $template = '
<div class="fp-upload-form mdl-align">
    <div class="fp-content-center">
        <form enctype="multipart/form-data" method="POST">
            <table >
                <tr class="{!}fp-setauthor">
                    <td class="mdl-right"><label>'.get_string('author', 'repository').'</label>:</td>
                    <td class="mdl-left"><input type="text"/></td>
                </tr>
                <tr class="{!}fp-setlicense">
                    <td class="mdl-right"><label>'.get_string('chooselicense', 'repository').'</label>:</td>
                    <td class="mdl-left"><select/></td>
                </tr>
                <tr class="{!}fp-recordaudio-recorder">
                    <td class="mdl-right"><label>'.get_string('record', 'repository_recordaudio').'</label>:</td>
                    <td class="mdl-left">'.$this->get_recorder().'</td></tr>
                </tr>
                <tr class="{!}fp-file">
                    <td class="mdl-right"></td>
                    <td class="mdl-left"><input type="file"/></td>
                </tr>
                <tr class="{!}fp-saveas">
                    <td class="mdl-right"></td>
                    <td class="mdl-left"><input type="text"/></td>
                </tr>
            </table>
        </form>
        <div><button class="{!}fp-upload-btn">'.get_string('upload', 'repository').'</button></div>
    </div>
</div> ';
        return preg_replace('/\{\!\}/', '', $template);
    }

    public function check_login() {
        // Needs to return false so that the "login" form is displayed (print_login())
        return false;
    }

    public function global_search() {
        // Plugin doesn't support global search, since we don't have anything to search
        return false;
    }

    public function get_listing($path='', $page = '') {
        return array();
    }

    /**
     * Process uploaded file
     * @return array|bool
     */
    public function upload($search_text) {
        global $USER, $CFG;

        $record = new stdClass();
        $record->filearea = 'draft';
        $record->component = 'user';
        $record->filepath = optional_param('savepath', '/', PARAM_PATH);
        $record->itemid   = optional_param('itemid', 0, PARAM_INT);
        $record->license  = optional_param('license', $CFG->sitedefaultlicense, PARAM_TEXT);
        $record->author   = optional_param('author', '', PARAM_TEXT);

        $context = context_user::instance($USER->id);
        $filename = required_param('recordaudio_filename', PARAM_FILE);
        $filedata = required_param('recordaudio_filedata', PARAM_RAW);
        $filedata = base64_decode($filedata);

        $fs = get_file_storage();
        $sm = get_string_manager();

        if ($record->filepath !== '/') {
            $record->filepath = file_correct_filepath($record->filepath);
        }

        $record->filename = $filename;

        if (empty($record->itemid)) {
            $record->itemid = 0;
        }

        $record->contextid = $context->id;
        $record->userid    = $USER->id;
        $record->source    = '';

        if (repository::draftfile_exists($record->itemid, $record->filepath, $record->filename)) {
            $existingfilename = $record->filename;
            $unused_filename = repository::get_unused_filename($record->itemid, $record->filepath, $record->filename);
            $record->filename = $unused_filename;
            $stored_file = $fs->create_file_from_string($record, $filedata);
            $event = array();
            $event['event'] = 'fileexists';
            $event['newfile'] = new stdClass;
            $event['newfile']->filepath = $record->filepath;
            $event['newfile']->filename = $unused_filename;
            $event['newfile']->url = moodle_url::make_draftfile_url($record->itemid, $record->filepath, $unused_filename)->out();

            $event['existingfile'] = new stdClass;
            $event['existingfile']->filepath = $record->filepath;
            $event['existingfile']->filename = $existingfilename;
            $event['existingfile']->url      = moodle_url::make_draftfile_url($record->itemid, $record->filepath, $existingfilename)->out();;
            return $event;
        } else {
            $stored_file = $fs->create_file_from_string($record, $filedata);
            return array(
                'url'=>moodle_url::make_draftfile_url($record->itemid, $record->filepath, $record->filename)->out(),
                'id'=>$record->itemid,
                'file'=>$record->filename);
        }
    }

    /**
     * Generate upload form
     */
    public function print_login($ajax = true) {
        $ret = array('nosearch'=>true, 'norefresh'=>true, 'nologin'=>true);
        $ret['upload'] = array('id'=>'repo-form');
        return $ret;
    }

    /**
     * supported return types
     * @return int
     */
    public function supported_returntypes() {
        return FILE_INTERNAL;
    }
}

// Note: microphone icon (pix/icon.png) by Creative Freedom, found via GettyIcons: http://www.gettyicons.com/free-icon/133/shimmer-icon-set/free-microphone-icon-png/