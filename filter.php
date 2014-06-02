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
 *
 * @package    repository_tripleplay
 * @copyright  2014 Tripleplay Services Ltd.
 * @author     Nuno Horta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/config.php');

class filter_tripleplay extends moodle_text_filter {

    /**
     * method to validate and return an iframe html element if a link from tripleplay plugin is found
     * this method is used in all all the text before it is output
     * @return string
     */
    public function filter($text, array $options = array()) {

        //performance +
        if (!is_string($text) or empty($text)) {
            return $text;
        }
        //performance ++
        if (stripos($text, '</a>') === false) {
            return $text;
        }

        $newtext = preg_replace_callback($re = '~\s*<a([^>]*\s*)>(.+?)</a>~', array($this, 'replace_link'), $text);

        if (empty($newtext) || $newtext === $text) {
            return $text;
        }

        return $newtext;
    }

    /**
     * If matches with a link from portal it will handle it to return an iframe
     * @return string
     */
    public function replace_link (array $matches){
        if (strpos($matches[0], '/portal/standalone.php')) {
            return $this->replace_embed($matches[0]);
        }else{
            return $matches[0];
        }
    }


    /**
     * 
     * @return string
     */
    public function replace_embed($text){

        global $USER;

        $dom = new DomDocument;
        $dom->loadHTML($text);
        $elements = $dom->getElementsByTagName('a');

        for ($n = 0; $n < $elements->length; $n++) {
            $item = $elements->item($n);
            $href = $item->getAttribute('href');
        }

        if (empty($href)) {
            return $text;
        }

        $href_parsed = parse_url($href);
        $scheme = $href_parsed["scheme"];
        $host   = $href_parsed["host"];
        $path   = $href_parsed["path"];

        if ($path == "/portal/standalone.php") {
            $query  = $href_parsed["query"];
            if (base64_decode($query)) {
                $result = $this->checkMetadata(base64_decode($query));
                $parameters = $result[0];
                $metadata   = $result[1];

                $url = $scheme."://".$host."/".$path."?".base64_encode("connectUsername=".$USER->username."&autoplay=false&".$parameters);

                return $this->getContent($url, $metadata);
            }
        }else{
            return $text; 
        }

    }

    public function checkMetadata($decoded_query){

        $metadata = strrpos($decoded_query, "extra");
        if ($metadata === false) { 
            $query = $decoded_query;
        }else{
            $result = explode('extra', $decoded_query);
            $query = $result[0];
            $metadata = $result[1];
        }

        return array($query, $metadata);
    }


    /**
     * method to return the iframe html to be displayed
     * @return string
     */
    public function getContent($url, $metadata){

        parse_str(substr($metadata, 2), $output);

        if($output['title'])
            $data = '<p><b>Title:</b><span> '.$output['title'].'</span></p>';

        if($output['synopsis'])
            $data .= '<p><b> Synopsis:</b><span> '.$output['synopsis'].'</span></p>';

        if($output['duration'])
            $data .= '<p><b> Duration:</b><span> '.$output['duration'].'</span></p>';

        if($output['owner'])
            $data .= '<p><b> Owner:</b><span> '.$output['owner'].'</span></p>';

        if($output['url'])
            $data .= '<p><a target="_blank" href="'.$this->getPortalUrl($url, $output['url']).'">Watch on Portal</a></p>';

        if ($data != '') {
            $data = '<div style="margin-top: 30px;">'.$data.'</div>';
            $init_div = '<div style="width:100%;display:inline-block;margin:15px 0px 10px;">';
            $iframe = '<iframe style="float:left;margin-right:20px;" width="600" frameborder="0" height="310" src="'.$url.'"></iframe>';
        }else{
            $init_div = '<div style="width:100%;display:inline-block;text-align:center;">';
            $iframe = '<iframe style="margin-right:20px;" width="600" frameborder="0" height="310" src="'.$url.'"></iframe>';
        }
        
        $end_div = '</div>';

        return $init_div.$iframe.$data.$end_div;
    }

    /**
     * Return a url to watch content on portal
     * @return string
     */
    public function getPortalUrl($fullUrl, $vodItem){

        $host = parse_url($fullUrl);
        $url = $host["scheme"]. "://" .$host["host"];
        
        return $url . '/portal/permalink.php?page=vod_title_information&params={&quot;vodItem&quot;:&quot;'.$vodItem.'&quot;}';
    }
}
?>
