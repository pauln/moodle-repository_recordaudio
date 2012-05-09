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
 * repository_youtube class
 *
 * @since 2.0
 * @package    repository
 * @subpackage recordaudio
 * @copyright  2012 Paul Nicholls
 * @author     Paul Nicholls <paul.nicholls@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class repository_recordaudio extends repository {

    /**
     * Youtube plugin constructor
     * @param int $repositoryid
     * @param object $context
     * @param array $options
     */
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        $this->start =1;
        $this->max = 27;
        $this->sort = optional_param('youtube_sort', 'relevance', PARAM_TEXT);
        parent::__construct($repositoryid, $context, $options);
    }

    public function check_login() {
        return !empty($this->keyword);
    }

    /**
     * Return search results
     * @param string $search_text
     * @return array
     */
    /*public function search($search_text) {
        $this->keyword = $search_text;
        $ret  = array();
        $ret['nologin'] = true;
        $ret['list'] = $this->_get_collection($search_text, $this->start, $this->max, $this->sort);
        return $ret;
    }*/

    /**
     * Private method to get youtube search results
     * @param string $keyword
     * @param int $start
     * @param int $max max results
     * @param string $sort
     * @return array
     */
    private function _get_collection($keyword, $start, $max, $sort) {
        $list = array();
        $this->feed_url = 'http://gdata.youtube.com/feeds/api/videos?q=' . urlencode($keyword) . '&format=5&start-index=' . $start . '&max-results=' .$max . '&orderby=' . $sort;
        $c = new curl(array('cache'=>true, 'module_cache'=>'repository'));
        $content = $c->get($this->feed_url);
        $xml = simplexml_load_string($content);
        $media = $xml->entry->children('http://search.yahoo.com/mrss/');
        $links = $xml->children('http://www.w3.org/2005/Atom');
        foreach ($xml->entry as $entry) {
            $media = $entry->children('http://search.yahoo.com/mrss/');
            $title = $media->group->title;
            $attrs = $media->group->thumbnail[2]->attributes();
            $thumbnail = $attrs['url'];
            $arr = explode('/', $entry->id);
            $id = $arr[count($arr)-1];
            $source = 'http://www.youtube.com/v/' . $id . '#' . $title;
            $list[] = array(
                'title'=>(string)$title,
                'thumbnail'=>(string)$attrs['url'],
                'thumbnail_width'=>150,
                'thumbnail_height'=>120,
                'size'=>'',
                'date'=>'',
                'source'=>$source
            );
        }
        return $list;
    }

    /**
     * Youtube plugin doesn't support global search
     */
    public function global_search() {
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

        $context = get_context_instance(CONTEXT_USER, $USER->id);
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
            
/*	Justin: This didn't seem necessary and I just used the same return array as the original upload repo.
            $info = array();
            $info['contextid'] = $stored_file->get_contextid();
            $info['itemid'] = $stored_file->get_itemid();
            $info['filearea'] = $stored_file->get_filearea();
            $info['component'] = $stored_file->get_component();
            $info['filepath'] = $stored_file->get_filepath();
            $info['title'] = $stored_file->get_filename();
            $list = array();
            $list[] = $info;
            return array('dynload'=>true, 'nosearch'=>true, 'nologin'=>true, 'list'=>$list);

         */
                
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
    
        global $CFG;
        $recorder = "";
        $url=$CFG->wwwroot.'/repository/recordaudio/assets/recorder.swf?gateway=form';
        // Justin: Here there was some code to disable the recordaudio_filename field, but since it was a hidden field, it messed it up somehow. I removed that code and the filename got passed through ok
        $callback = urlencode("(function(a, b){d=document;d.g=d.getElementById;fn=d.g('recordaudio_filename');fn.value=a;fd=d.g('recordaudio_filedata');fd.value=b;f=fn;while(f.tagName!='FORM')f=f.parentNode;f.repo_upload_file.type='hidden';f.repo_upload_file.value='bogus.mp3';f.nextSibling.getElementsByTagName('button')[0].click();})");
        $flashvars="&callback={$callback}&filename=new_recording";

        $recorder = '<div style="position:absolute; top:0;left:0;right:0;bottom:0; background-color:#fff;">
                <input type="hidden"  name="recordaudio_filename" id="recordaudio_filename" />
                <textarea name="recordaudio_filedata" id="recordaudio_filedata" style="display:none;"></textarea>
                <div id="onlineaudiorecordersection" style="margin:20% auto; text-align:center;">
                    <object id="onlineaudiorecorder" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="215" height="138">
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
        $ret = array();
        /*$search = new stdClass();
        $search->type = 'hidden" />'.$recorder.'<input style="display:none';
        $search->id   = 'recordaudio_recorder';
        $search->name = 's';
        $search->label = get_string('record', 'repository_recordaudio').': ';*/
        /*$ret['login'] = array($search);
        $ret['login_btn_label'] = get_string('upload', 'repository') . '" disabled type="button';
        $ret['login_btn_action'] = 'search';*/
        /*$ret['login_btn_id'] = 'repo-form';*/
        $ret['upload'] = array('label'=>$recorder, 'id'=>'repo-form');
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
