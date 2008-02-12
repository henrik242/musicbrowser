<?php

/**
 *   $Id: streamlib.php,v 1.32 2008-02-12 20:53:42 mingoto Exp $
 *
 *   This file is part of Music Browser.
 *
 *   Music Browser is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   Music Browser is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with Music Browser.  If not, see <http://www.gnu.org/licenses/>.
 *
 *   Copyright 2006-2008 Henrik Brautaset Aronsen
 */

class MusicBrowser {
  
  var $columns = 5;
  var $infoMessage = "";
  var $headingThreshold = 14;
  var $thumbSize = 150;
  var $columnWidth = 300;
  var $allowLocal = false;
  var $homeName, $streamLib, $fileTypes, $template, $charset;
  var $player, $maxPlaylistSize, $slimserverUrl, $slimserverUrlSuffix;
  var $enabledPlay = array();
  var $directFileAccess = false;
  
  /**
   * @param array $config Assosciative array with configuration
   */  
  function MusicBrowser($config) {
    if ($config['debug']) {
      ini_set("error_reporting", E_ALL);
      ini_set("display_errors", 1);
    } else {
      ini_set("display_errors", 0);
    }
    if (!function_exists('preg_match')) {
      $this->fatal_error("Your PHP installation lacks the PCRE extension");
    }
    if (!is_readable($config['template'])) {
      $this->fatal_error("The \$config['template'] \"{$config['template']}\" isn't readable");
    }
    $this->resolve_config($config);    
    $this->streamLib = new StreamLib();
  }

  function resolve_config($config) {
    $this->resolve_url($config['url']); // need to resolve url before path
    $this->resolve_path($config['path']);
  
    foreach ($config as $key => $value) {
      switch ($key) {
        case 'url';
        case 'path';
          break;
        case "allowLocal":
          if (count($value) == 0) {
            $this->allowLocal = true;
          } else {
            foreach ($value as $host) {
              if (empty($host)) continue;
              if (preg_match($host, gethostbyaddr($_SERVER['REMOTE_ADDR'])) > 0
                  || preg_match($host, gethostbyname($_SERVER['REMOTE_ADDR'])) > 0) {
                $this->allowLocal = true;
              }
            }
          }
          break;
        case "enableM3u":
          if ($value) $this->enabledPlay[] = "m3u";
          break;
        case "enableAsx":
          if ($value) $this->enabledPlay[] = "asx";
          break;
        case "enablePls":  
          if ($value) $this->enabledPlay[] = "pls";
          break;
        case "enableFlash":  
          if ($value) $this->enabledPlay[] = "flash";
          break;
        default:
          $this->$key = $value;
          break;
      }
    }
    
    if ($this->allowLocal) {
      if (strlen($this->slimserverUrl) > 0) $this->enabledPlay[] = "slim";
      if (strlen($this->player) > 0) $this->enabledPlay[] = "player";
    }
  }

  function fatal_error($message) {
    echo "<html><body text=red>ERROR: $message</body></html>";
    exit(0);
  }
  
  /**
   * Display requested page.
   */
  function show_page() {
    $fullPath = PATH_FULL;

    if ((is_dir($fullPath) || is_file($fullPath)) && isset($_GET['stream'])) {
      # If streaming is requested, do it
      $this->stream_all($_GET['stream']);
      exit(0);
    } elseif (is_file($fullPath)) {
      # If the path is a file, download it
      $this->streamLib->stream_file_auto($fullPath);
      exit(0);
    } 

    # List of files and folders
    $entries = $this->list_folder($fullPath);

    # Set stream/play type from $_POST or $_COOKIE
    $this->set_stream_type();

    # get all content for template
    $coverImage = $this->show_cover();
    $topPath = $this->show_header();
    $content = $this->show_folder($entries);
    $options = $this->show_options();

    if (isset($_GET['message'])) {
      $this->add_message($_GET['message']);
    }

    $folder = URL_FULL . "?path=" . $this->path_encode(PATH_RELATIVE);
    $displayTitle = PATH_RELATIVE == "" ? $this->homeName : $this->homeName . " -";
    $flashPlayer = STREAMTYPE == "flash" ? $this->show_flashplayer() : "";
    $rssUrl = htmlentities("$folder&stream=rss");
    $rssTitle = PATH_RELATIVE . " podcast";

    $search = array("/%top_path%/", "/%columns%/", "/%cover_image%/", "/%error_msg%/", 
                    "/%stream_options%/", "/%content%/", "/%folder_path%/", "/%thumb_size%/",
                    "/%rss_url%/", "/%rss_title%/", "/%flash_player%/", "/%display_title%/", 
                    "/%display_path%/", "/%column_width%/", "/%charset%/");
    $replace = array($topPath, $this->columns, $coverImage, $this->infoMessage, 
                     $options, $content, $folder, $this->thumbSize,
                     $rssUrl, $rssTitle, $flashPlayer, $displayTitle, 
                     PATH_RELATIVE, $this->columnWidth, $this->charset);

    $template = implode("", file($this->template));
    print preg_replace($search, $replace, $template);
    exit(0);
  }
  
  /**
   * Format music folder content as HTML.
   *
   * @return string Formatted HTML with folder content
   */
  function show_folder($items) {

    $output = "";
    if (count($items) > 0) {
      $groupList = $this->group_items($items);
      
      foreach ($groupList as $first => $group) {
        $entry = "";
        if (count($groupList) > 1) {
          $entry .= "<tr><th colspan={$this->columns}>$first</th></tr>\n";
        }
        $rows = ceil(count($group) / $this->columns);
        $rowcolor = "";
        for ($row = 0; $row < $rows; $row++) {
          if ($rowcolor == "odd") {
            $rowcolor = "even";
          } else {
            $rowcolor = "odd";
          }
          $entry .= "<tr class=$rowcolor>";
          for ($i = 0; $i < $this->columns; $i++) {
            $cell = $row + ($i * $rows);
            $item = @ $group[$cell];
            $urlPath = $this->path_encode(PATH_RELATIVE . "/$item");
            
            $entry .= '<td class=cell>';
            if (empty($item)) {
              $entry .= "&nbsp;";
            } elseif (is_dir(PATH_FULL . "/$item")) {
              # Folder link
              $image = $this->show_folder_cover(PATH_RELATIVE . "/$item");
              $displayItem = $this->word_wrap($item);
              $entry .= "$image<a title=\"Play files in this folder\" href=\"" . $this->play_url($urlPath) 
                . "\"><img border=0 alt=\"|&gt; \" src=\"play.gif\"></a>\n"
                . "<a class=folder href=\"" . URL_RELATIVE . "?path=$urlPath\">$displayItem/</a>\n";
            } else {
              # File link
              $displayItem = $this->word_wrap($item);
              $entry .= "<a href=\"" . $this->direct_link(PATH_RELATIVE . "/$item") . "\">"
                . "<img src=\"download.gif\" border=0 title=\"Download this song\" alt=\"[Download]\"></a>\n"
                . "<a class=file title=\"Play this song\" href=\"" . $this->play_url($urlPath) 
                . "\">$displayItem</a>\n";
            }
            $entry .= "</td>\n";
          }
          $entry .= "</tr>\n";
        }      
        $output .= $entry;
      }
    }
    return $output;
  }

  function direct_link($urlPath) {
     if ($this->directFileAccess) {
       return $this->path_encode($urlPath);
     } 
     return URL_RELATIVE . "?path=" . $this->path_encode("$urlPath");
  }

  function play_url($urlPath) {
    $streamUrl = URL_RELATIVE . "?path=" . $urlPath . "&amp;stream";
    if (STREAMTYPE == "flash") {
       # Need to encode url entities twice
       $streamUrl = preg_replace("/%([0-9a-f]{2})/i", "%25\\1", $streamUrl);
       return "javascript:loadFile('mpl',{file:encodeURI('$streamUrl=asx')})";
    }
    return "$streamUrl=" . STREAMTYPE;
  }

  function word_wrap($item) {
    if (strlen($item) > 40) {
      $item = preg_replace("/_/", " ", $item);
      $pos = strpos($item, " ");
      if ($pos > 40 || !$pos) {
        $item = substr($item, 0, 30) . " " . $this->word_wrap(substr($item, 30));
      }
    }
    return $item;
  }

  function show_options() {
    $select = array();
    foreach ($this->enabledPlay as $list) {
      $select[$list] = "";
    }
    if (array_key_exists(STREAMTYPE, $select)) {
      $select[STREAMTYPE] = 'CHECKED';
    }
    $output = "";
    foreach ($select as $type => $checked) {
      switch ($type) {
        case "player":
          $display = "Play on server";
          break;
        case "slim":
          $display = "Play on Squeezebox";
          break;
        default:
          $display = $type;
      }
      $output .= "<input type=radio name=streamtype value=$type "
               . " onClick=\"document.streamtype.submit()\" $checked>$display\n";
    }
    return $output;
  }

  /**
   * Group $items by initial, with a minimum amount in each group 
   * @see $this->headingThreshold
   */
  function group_items($items) {
    natcasesort($items);
    $groupList = $group = array();
    $to = $from = "";
    foreach ($items as $item) {
        $current = strtoupper($item{0});
        
        if (strlen($from) == 0) {
          $from = $current;
        }
        
        if ($to == $current || count($group) < $this->headingThreshold) {
          $group[] = $item;
        } else {
          $groupList = $this->add_group($groupList, $group, $from, $to);
          $group = array($item);
          $from = $current;
        }
        $to = $current;
    }
    if (count($group) > 0) {
      $groupList = $this->add_group($groupList, $group, $from, $to);
    }
    return $groupList;
  }

  function add_group($groupList, $group, $from, $to) {
    if ($from == $to) {
      $groupList[$from] = $group;
    } else { 
      $groupList["$from&ndash;$to"] = $group;
    }
    return $groupList;  
  }

  /**
   * List folder content.
   * @return array Array with all allowed file and folder names
   */
  function list_folder($path) {
    $folderHandle = dir($path);
    $entries = array();
    while (false !== ($entry = $folderHandle->read())) {
      foreach ($this->hideItems as $hideItem) {
        if (preg_match($hideItem, $entry)) continue 2;
      }
      $fullPath = "$path/$entry";
      if (is_dir($fullPath) || (is_file($fullPath) && $this->valid_suffix($entry))) {
        $entries[] = $entry;
      }
    }
    $folderHandle->close();
    natcasesort($entries);
    return $entries;
  }

  /**
   * Fetches stream/play type from $_POST or $_COOKIE.
   * @return string streamtype
   */
  function set_stream_type() {
    $setcookie = false;
    $streamType = "";
    if (isset($_POST['streamtype'])) {
      $streamType = $_POST['streamtype'];
      $setcookie = true;
    } elseif (isset($_COOKIE['streamtype'])) {
      $streamType = $_COOKIE['streamtype'];
    }
    if (in_array($streamType, $this->enabledPlay)) {
      if ($setcookie) setcookie('streamtype', $streamType);
      define('STREAMTYPE', $streamType);
    } else {
      define('STREAMTYPE', @ $this->enabledPlay[0]);
    }
  }

  function show_folder_cover($pathRelative) {
    $image = "";
    if ($this->folderCovers) {
      $coverImage = $this->cover_image($pathRelative);
      if (!empty($coverImage)) {
        $urlPath = $this->path_encode($pathRelative);
        $image ="<a href=\"" . URL_RELATIVE . "?path=$urlPath\"><img src=\"$coverImage\" border=0 width=100 height=100 alt=\"\"></a><br>";
      }
    }
    return $image;
  }  

  /**
   * @return string Formatted HTML with cover image (if any)
   */
  function show_cover($pathRelative = PATH_RELATIVE) {
    $link = $this->cover_image($pathRelative);
    if (!empty($link)) {
      return "<a href=\"$link\"><img border=0 src=\"$link\" width={$this->thumbSize} "
                 . "height={$this->thumbSize} align=left></a>";
    }
    return "";
  }
  
  function cover_image($pathRelative = PATH_RELATIVE) {
    $covers = array("cover.jpg", "Cover.jpg", "folder.jpg", "Folder.jpg", "cover.gif", "Cover.gif",
                  "folder.gif", "Folder.gif");
    foreach ($covers as $cover) {
      if (is_readable(PATH_ROOT . "/$pathRelative/$cover")) {
        $pathEncoded = $this->path_encode("$pathRelative/$cover", false);
        if ($this->directFileAccess) {
          return $pathEncoded;
        } else {
          return URL_RELATIVE . "?path=" . $pathEncoded;
        }
      }
    }
    return "";
  }

  /**
   * @return string Formatted HTML with bread crumbs for folder
   */
  function show_header() {
    $path = PATH_RELATIVE;
    $parts = explode("/", trim($path, "/ "));
    if ($parts[0] == '') {
      $parts = array();
    }
    if (count($parts) > 0) {
      $items = array("<b><a href=\"" . URL_RELATIVE . "?path=\">{$this->homeName}</a></b>");
    } else {
      $items = array("<b>{$this->homeName}</b>");
    }
    $currentPath = $encodedPath = "";
    for ($i = 0; $i < count($parts); $i++) {
      $currentPath .= "/{$parts[$i]}";
      $encodedPath = $this->path_encode($currentPath);
      if ($i < count($parts) - 1) {
        $items[] = "<b><a href=\"" . URL_RELATIVE . "?path=$encodedPath\">{$parts[$i]}</a></b>\n";
      } else {
        $items[] = "<b>{$parts[$i]}</b>";
      }
    }
    $output = implode(" &raquo; ", $items);

    # Output "play all"
    $output .= "&nbsp;&nbsp;<a href=\"" . $this->play_url($encodedPath) . "\"><img 
      src=\"play.gif\" border=0 title=\"Play all songs in this folder as " . STREAMTYPE . "\"
      alt=\"Play all songs in this folder as " . STREAMTYPE . "\"></a>";
    return $output;
  }

  /**
   * Checks if $entry has any of the $fileTypes
   *
   * @return boolean True if valid.
   */
  function valid_suffix($entry) {
    foreach ($this->fileTypes as $suffix) {
      if (preg_match("/\." . $suffix . "$/i", $entry)) {
         return true;
      }
    }
    return false;
  }

  /**
   * Find all items in a folder recursively.
   */
  function folder_items($folder, $allItems) {
    $fullPath = PATH_ROOT . "/$folder";
    $entries = $this->list_folder($fullPath);
    foreach ($entries as $entry) {
      if (count($allItems) >= $this->maxPlaylistSize) {
        return $allItems;
      }
      if (is_file("$fullPath/$entry")) {
        $allItems[] = "$folder/$entry";
      } else {
        $allItems = $this->folder_items("$folder/$entry", $allItems);
      }
    }
    return $allItems;
  }

  /**
   * Stream folder or file.
   */
  function stream_all($type) {
    if ($type == "slim" && $this->allowLocal) {
      $this->play_slimserver(PATH_RELATIVE);
      return;
    }
    
    $fullPath = PATH_FULL;
    $name = $this->pathinfo_basename($fullPath);
    if (empty($name)) $name = "playlist";
    $items = array();
    
    if (is_dir($fullPath)) {
      $items = $this->folder_items(PATH_RELATIVE, $items);
      natcasesort($items);
    } else {
      $items[] = PATH_RELATIVE;
    }
    if (count($items) == 0) {
       $this->add_message("No files to play in <b>$name</b>");
       return;
    }
    $entries = array();
    $withTimestamp = false;
    if ($type == 'rss') {
      $withTimestamp = true;
    } 
    foreach ($items as $item) {
      $entries[] = $this->entry_info($item, $withTimestamp);
    }

    switch ($type) {
      case "rss":
        $url = URL_FULL . "?path=" . $this->path_encode(PATH_RELATIVE) . "&stream=rss";
        $coverImage = $this->cover_image();
        $image = "";
        if (!empty($coverImage)) {
          $image = URL_ROOT . "/$coverImage";
        }
        $this->streamLib->playlist_rss($entries, $name, $url, $image, $this->charset);
        break;
      case "m3u":
        $this->streamLib->playlist_m3u($entries, $name);
        break;
      case "pls":
        $this->streamLib->playlist_pls($entries, $name);
        break;
      case "asx":
        $this->streamLib->playlist_asx($entries, $name, $this->charset);
        break;
      case "player":
        if ($this->allowLocal) {
          $this->play_server($items);
        }
        break;
    }
  }

  /**
   * Info for entry in playlist.
   */
  function entry_info($item, $withTimestamp = false) {
    $search = array("|\.[a-z0-9]{1,4}$|i", "|/|");
    $replace = array("", " - ");
    $name = preg_replace($search, $replace, trim($item, "/"));
    if ($this->directFileAccess) {
      $url = URL_ROOT . "/" . $this->path_encode($item, false);
    } else {
      $url = URL_FULL . "?path=" . $this->path_encode($item);
    }
    $entry = array('title' => $name, 'url' => $url);
    if ($withTimestamp) {
      $entry['timestamp'] = filectime(PATH_ROOT . "/$item");
    }
    return $entry;
  }

  /**
   * play_server uses system() and might be VERY UNSAFE!
   */
  function play_server($items) {
    $args = "";
    foreach ($items as $item) {
      $args .= "\"" . PATH_ROOT . "/$item\" ";
    }
    system("{$this->player} $args >/dev/null 2>/dev/null &", $ret);
    if ($ret === 0) {
      $this->add_message("Playing requested file(s) on server");
    } else {
      $this->add_message("Error playing file(s) on server.  Check the error log.");
    }
    $this->reload_page(); //exits
  }

  function play_slimserver($item) {
     $action = "/status_header.html?p0=playlist&p1=play&p2=" . urlencode("file://" . PATH_FULL);
     $player = "&player=" . urlencode($this->slimserverPlayer);
     $url = $this->slimserverUrl . $action . $player;
     $data = $this->http_get($url);
     if (strlen($data) == 0) {
       $this->add_message("Error reaching slimserver");
     } else {
       $this->add_message("Playing requested file(s) on Squeezebox");
     }
     $this->reload_page(); //exits
  }
  
  /**
   * Redirect to current folder page.
   */
  function reload_page() {
    $path = "";
    if (defined('PATH_RELATIVE')) {
      if (is_file(PATH_FULL)) {
        $path = preg_replace("|/[^/]+$|", "", PATH_RELATIVE);
      } else {
        $path = PATH_RELATIVE;
      }
    }
    $url = URL_FULL . "?path=" . $this->path_encode($path);
    $url .= "&message=" . urlencode($this->infoMessage);
    header("Location: $url");
    exit(0);
  }

  function http_get($url) {
    if (!ini_get('allow_url_fopen')) {
      $this->add_message("'allow_url_fopen' in php.ini is set to false");
      return;
    }
    if (!($fp = fopen($url, "r"))) {
      $this->add_message("Could not open URL " . $url);
      return;
    }
    $data = "";
    while ($d = fread($fp, 4096)) { 
      $data .= $d; 
    };
    fclose($fp); 
    return $data;
  }

  /**
   * Add message to be displayed on top.
   */
  function add_message($msg) {
    $this->infoMessage .= "$msg<br>\n";
  }

  /**
   * Try to resolve a safe path.
   */
  function resolve_path($rootPath) {
    if (empty($rootPath)) $this->directFileAccess = true;
    if (!empty($rootPath) && !is_dir($rootPath)) {
      $this->fatal_error("The \$config['path'] \"$rootPath\" isn't readable");
    }
    if (empty($rootPath)) {
       $rootPath = getcwd();
    }
    
    $relPath = "";
    if (isset($_GET['path'])) {
      # Remove " and \
      $getPath = preg_replace(array("|\\x5c|", "|\x22|", "|%5c|"), 
                              array("", "", ""), $_GET['path']);
      $getPath = stripslashes($getPath);
      
      if (is_readable("$rootPath/$getPath")) {
        $relPath = $getPath;
      } else {
        $this->add_message("The path <i>$getPath</i> is not readable.");
        $this->reload_page(); // exits
      }
    }
    $fullPath = "$rootPath/$relPath";
    # Avoid funny paths
    $realFullPath = realpath($fullPath);
    $realRootPath = realpath($rootPath);
    if ($realRootPath != substr($realFullPath, 0, strlen($realRootPath)) || !(is_dir($fullPath) || is_file($fullPath))) {
       $relPath = "";
       $fullPath = $rootPath;
    }
    define('PATH_ROOT', $rootPath);    # e.g. /mnt/music
    define('PATH_RELATIVE', $relPath); # e.g. Covenant/Stalker.mp3
    define('PATH_FULL', $fullPath);    # e.g. /mnt/music/Covenant/Stalker.mp3
  }
  
  function resolve_url($rootUrl) {
    if (empty($rootUrl)) {
      $folder = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
      $root = 'http://' . $_SERVER['HTTP_HOST'] . $folder;
    } else {
      $root = trim($rootUrl, '/ ');
      if (!preg_match('#^https?:/(/[a-z0-9]+[a-z0-9:@-\.]+)+$#i', $root)) {
        $this->fatal_error("The \$config['url'] \"{$root}\" is invalid");
      }
    } 
    $relative = $this->pathinfo_basename($_SERVER['SCRIPT_NAME']);
    define('URL_ROOT', $root);             # e.g. http://mysite.com
    define('URL_RELATIVE', $relative);     # e.g. musicbrowser
    define('URL_FULL', "$root/$relative"); # e.g. http://mysite.com/musicbrowser
  }

  function pathinfo_basename($file) {
     return array_pop(explode("/", $file));
  }

  /**
   * Encode a fairly readable path for the URL.
   */
  function path_encode($path, $encodespace = true) {
     $search = array("|^%2F|", "|%2F|");
     $replace = array("", "/");
     if ($encodespace) {
       $search[] = "|%20|";
       $replace[] = "+";
     }
     return preg_replace($search, $replace, rawurlencode($path)); 
  }

  function show_flashplayer() {
    return <<<EOF
<script type="text/javascript" src="swfobject.js"></script>
<div id="player">JW FLV Media Player</div>
<script type="text/javascript">
<!--
function thisSong(swf) {
  if(navigator.appName.indexOf("Microsoft") != -1) {
    return window[swf];
  } else {
    return document[swf];
  }
};
function loadFile(swf,obj) {
  thisSong(swf).loadFile(obj);
};
var so = new SWFObject('mediaplayer.swf', 'mpl', '{$this->columnWidth}', '{$this->thumbSize}', '8');
so.addParam('allowscriptaccess', 'always');
so.addParam('allowfullscreen', 'false');
so.addVariable('height', '{$this->thumbSize}');
so.addVariable('width', '{$this->columnWidth}');
so.addVariable('displaywidth', '0');
so.addVariable('showstop', 'true');
so.addVariable('autostart', 'true');
so.addVariable('usefullscreen', 'false');
so.addVariable('shuffle', 'false');
so.addVariable('enablejs', 'true');
so.addVariable('type', 'mp3');
so.addVariable('repeat', 'list');
so.write('player');
-->
</script>
EOF;
  }
}



class StreamLib {

  /**
   * @param array $entries Array of arrays with keys moreinfo, url, starttime, duration, title, author & copyright
   * @param string $name Stream name
   */
  function playlist_asx($entries, $name = "playlist", $charset = "iso-8859-1") {

     $output = "<asx version=\"3.0\">\n";
     $output .= "<param name=\"encoding\" value=\"utf-8\" />\n";
     foreach ($entries as $entry) {
        $title = $this->convert_to_utf8($entry['title'], $charset);
     
        $output .= "  <entry>\n";
        $output .= "    <ref href=\"{$entry['url']}\" />\n";
        if (isset($entry['moreinfo']))  $output .= "    <moreinfo href=\"{$entry['moreinfo']}\" />\n";
        if (isset($entry['starttime'])) $output .= "    <starttime value=\"{$entry['starttime']}\" />\n";
        if (isset($entry['duration']))  $output .= "    <duration value=\"{$entry['duration']}\" />\n";
        if (isset($entry['title']))     $output .= "    <title>$title</title>\n";
        if (isset($entry['author']))    $output .= "    <author>{$entry['author']}</author>\n";
        if (isset($entry['copyright'])) $output .= "    <copyright>{$entry['copyright']}</copyright>\n";
        $output .= "  </entry>\n";
     }
     $output .= "</asx>\n";
     
     $this->stream_content($output, "$name.asx", "audio/x-ms-asf");
  }

  function convert_to_utf8($entry, $fromCharset = "iso-8859-1") {
    if ($fromCharset != "utf-8") {
      $entry = htmlentities($entry, ENT_COMPAT, strtoupper($fromCharset));
      $entry = html_entity_decode($entry, ENT_COMPAT, "UTF-8");
    }
    return $entry;
  }

  /**
   * @param array $entries Array of arrays with keys url, title
   * @param string $name Stream name
   */
  function playlist_pls($entries, $name = "playlist") {
     $output = "[playlist]\n";
     $output .= "X-Gnome-Title=$name\n";
     $output .= "NumberOfEntries=" . count($entries) . "\n";
     $counter = 1;
     foreach ($entries as $entry) {
        $output .= "File$counter={$entry['url']}\n"
                 . "Title$counter={$entry['title']}\n"
                 . "Length$counter=-1\n";
        $counter++;
     }
     
     $output .= "Version=2\n";
     
     $this->stream_content($output, "$name.pls", "audio/x-scpls");
  }

  /**
   * @param array $entries Array of arrays with keys url, title
   * @param string $name Stream name
   */
  function playlist_m3u($entries, $name = "playlist") {
    $output = "#EXTM3U\n";
    foreach ($entries as $entry) {
      $output .= "#EXTINF:0, {$entry['title']}\n"
               . "{$entry['url']}\n";
    }
     
    $this->stream_content($output, "$name.m3u", "audio/x-mpegurl");
  }

  /**
   * Aka podcast.
   *
   * @param array $entries Array of arrays with keys url, title, timestamp
   * @param string $name Stream name
   * @param string $link The link to this rss
   * @param string $image Album cover (optional)
   */
  function playlist_rss($entries, $name = "playlist", $link, $image = "", $charset = "iso-8859-1") {
    $link = htmlspecialchars($link);
    $name = htmlspecialchars($name);
    $output = "<?xml version=\"1.0\" encoding=\"$charset\"?>\n"
            . "<rss xmlns:itunes=\"http://www.itunes.com/DTDs/Podcast-1.0.dtd\" version=\"2.0\">\n"
            . "  <channel><title>$name</title><link>$link</link>\n";
    if (!empty($image)) {
      $output .= "  <image><url>$image</url></image>\n";
    }
    foreach ($entries as $entry) {
      $date = date('r', $entry['timestamp']);
      $url = htmlspecialchars($entry['url']);
      $title = htmlspecialchars($entry['title']);
      $output .= "  <item><title>$title</title>\n"
               . "    <enclosure url=\"$url\" type=\"audio/mpeg\" />\n"
               . "    <guid>$url</guid>\n"
               . "    <pubDate>$date</pubDate>\n"
               . "  </item>\n";
    }
    $output .= "</channel></rss>\n";
    $this->stream_content($output, "$name.rss", "text/xml", "inline");
  }

  /**
   * @param array $entries Array of arrays with keys url, title
   * @param string $name Stream name
   */
  function stream_show_entries($entries, $name = "playlist") {
    $output = "<html><head><title>$name</title></head><body><ul>";
    foreach ($entries as $entry) {
      $output .= "<li><a href=\"{$entry['url']}\">{$entry['title']}</a>\n";
    }
    $output .= "</ul></body></html>";
    print $output;
    exit(0);
  }

  /**
   * @param string $content Content to stream
   * @param string $name Stream name with suffix
   * @param string $mimetype Mime type
   */
  function stream_content($content, $name, $mimetype, $disposition = "attachment") {
     header("Content-Disposition: $disposition; filename=\"$name\"", true);
     header("Content-Type: $mimetype", true);
     header("Content-Length: " . strlen($content));
     print $content;
     exit(0);
  }

  /**
   * @param string $file Filename with full path
   */
  function stream_mp3($file) {
     $this->stream_file($file, "audio/mpeg");
  }

  /**
   * @param string $file Filename with full path
   */
  function stream_gif($file) {
     $this->stream_file($file, "image/gif", false);
  }  

  /**
   * @param string $file Filename with full path
   */
  function stream_jpeg($file) {
     $this->stream_file($file, "image/jpeg", false);
  }

  /**
   * @param string $file Filename with full path
   */
  function stream_png($file) {
     $this->stream_file($file, "image/png", false);
  }

  /**
   * Streams a file, mime type is autodetected (Supported: mp3, gif, png, jpg)
   *
   * @param string $file Filename with full path
   */
  function stream_file_auto($file) {
     $suffix = strtolower(pathinfo($file, PATHINFO_EXTENSION));

     switch ($suffix) {
        case "mp3":
          $this->stream_mp3($file);
          break;
        case "gif":
          $this->stream_gif($file);
          break;
        case "png";
          $this->stream_png($file);
          break;
        case "jpg":
        case "jpeg":
          $this->stream_jpeg($file);
          break;
        default:
          $this->stream_file($file, "application/octet-stream");
          break; 
     }
  }

  /**
   * @param string $file Filename with full path
   * @param string $mimetype Mime type
   * @param boolean $isAttachment Add "Content-Disposition: attachment" header (optional, defaults to true)
   */
  function stream_file($file, $mimetype, $isAttachment = true) {
     $filename = array_pop(explode("/", $file));
     header("Content-Type: $mimetype");
     header("Content-Length: " . filesize($file));
     if ($isAttachment) header("Content-Disposition: attachment; filename=\"$filename\"", true);

     $this->readfile_chunked($file);
     exit(0);
  }

  /**
   * @see http://no.php.net/manual/en/function.readfile.php#54295
   */
  function readfile_chunked($filename, $retbytes = true) {
    $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
    $buffer = "";
    $cnt = 0;
    
    $handle = fopen($filename, "rb");
    if ($handle === false) {
      return false;
    }
    while (!feof($handle)) {
      $buffer = fread($handle, $chunksize);
      echo $buffer;
      @ob_flush();
      flush();
      if ($retbytes) {
        $cnt += strlen($buffer);
      }
    }
    $status = fclose($handle);
    if ($retbytes && $status) {
      return $cnt; // return num. bytes delivered like readfile() does.
    }
    return $status;
  }
}
?>