<?php

/**
 *   This file is part of Music Browser.
 *
 *   Music Browser is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
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

define('M3U', 'm3u');
define('ASX', 'asx');
define('FLASH', 'flash');
define('SERVER', 'server');
define('PLS', 'pls');
define('SLIM', 'slim');
define('RSS', 'rss');
define('XBMC', 'xbmc');
define('XSPF', 'xspf');

define('VERSION', '0.24-svn');

class MusicBrowser {
  
  var $headingThreshold = 24;
  var $allowLocal = false;
  var $enabledPlay = array();
  var $homeName, $streamLib, $fileTypes, $template, $charset, $searchDB, $securePath, $serverPlayer, $hideItems;
  var $maxPlaylistSize, $slimserverUrl, $slimserverUrlSuffix, $path, $url, $columns, $thumbSize, $streamtype, $shuffle;

  /**
   * @param array $config Assosciative array with configuration
   */  
  function MusicBrowser($config) {
    if ($config['debug']) {
      ini_set('error_reporting', E_ALL);
      ini_set('display_errors', 1);
    } else {
      ini_set('display_errors', 0);
    }
    if (!function_exists('preg_match')) {
      $this->show_fatal_error('The preg_match function does not exist. Your PHP installation lacks the PCRE extension');
    }
    if (!is_readable($config['template'])) {
      $this->show_fatal_error("The \$config['template'] \"{$config['template']}\" isn't readable");
    }
    session_start();
    
    $this->resolve_config($config);    
    $this->workaround_missing_functions();    
    $this->streamLib = new StreamLib();
  }

  /**
   * Resolves the configuration.
   * @param array $config
   */
  function resolve_config($config) {
    foreach ($config as $key => $value) {
      switch ($key) {
        case 'url';
          $this->url = new Url($config['url']);
          if ($this->url->full === NULL) {
            $this->show_fatal_error(Logger::pop());  //Exits
          }
          break;
        case 'path';
          $this->path = new Path($config['path'], $this->securePath);
          if ($this->path->full === NULL) {
            $this->show_fatal_error(Logger::pop());  //Exits
          }
          break;
        case 'allowLocal':
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
        default:
          $this->$key = $value;
          break;
      }
    }
    
    if (!$this->allowLocal || strlen($this->slimserverUrl) == 0) $this->disable_play_method(SLIM);
    if (!$this->allowLocal || strlen($this->serverPlayer) == 0) $this->disable_play_method(SERVER);
    if (!$this->allowLocal || strlen($this->xbmcUrl) == 0) $this->disable_play_method(XBMC);
  }

  /**
   * Disable an allowed play method.
   * @param string $disable Play method to disable
   */
  function disable_play_method($disable) {
    foreach ($this->enabledPlay as $key => $var) {
      if ($var == $disable) {
        unset($this->enabledPlay[$key]);
        break;
      }
    }
  }

  function workaround_missing_functions() {
    $message = "";
    if (!function_exists('mb_substr')) {
      $message .= "Warning: Your PHP installation lacks the Multibyte String Functions extension<br>";
      function mb_substr($str, $start, $length = 1024, $encoding = false) {
        return substr($str, $start, $length);      
      }  
      function mb_convert_case($str, $mode, $encoding = false) {
        return ucwords($str);      
      }  
    }
    if (!function_exists('utf8_encode')) {
      $message .= "Warning: Your PHP installation lacks the XML Parser Functions extension<br>";
      function utf8_encode($str) {
        return $str;
      }  
    }
    if (!function_exists('normalizer_normalize')) {
      $message .= "Warning: Your PHP installation lacks the Internationalization Functions extension<br>";
      function normalizer_is_normalized($str) {
        return true;
      }
      function normalizer_normalize($str) {
        return $str;
      }
    }
    if (!empty($message) && $this->debug) {
      Logger::log($message);
    }
  }

  /**
   * Exit with error.
   * @param string $message Error message
   */
  function show_fatal_error($message) {
    echo "<html><body text=red>ERROR: $message</body></html>";
    exit(0);
  }
  
  
  /**
   * Display requested page, or deliver ajax content.
   */
  function show_page() {

    if (isset($_GET['search'])) {
      $this->show_search(); // Exits
    }    
    if (isset($_GET['builddb'])) {
      $this->show_builddb($this->path->root); // Exits
    }    
    if (isset($_GET['messages'])) {
      $this->show_messages(); // Exits
    }
    if (isset($_GET['showstreamtype'])) {
      $this->show_streamtype(); // Exits
    }

    if ((is_dir($this->path->full) || is_file($this->path->full)) && isset($_GET['stream'])) {
      # If streaming is requested, do it
      $this->stream($_GET['stream'], @$_GET['shuffle']);
      exit(0);
    } elseif (is_file($this->path->full)) {
      # If the path is a file, download it
      $this->streamLib->stream_file_auto($this->path->full);
      exit(0);
    } 

    $this->resolve_streamtype_and_shuffle();

    if (isset($_GET['content'])) {
      $this->show_content($this->path->full); // Exits
    }

    $search = array("/%folder%/", "/%flash_player%/", "/%searchfield%/", "/%admin%/", "/%version%/");
    $replace = array(addslashes($this->path->relative), $this->html_flashplayer(), $this->html_searchfield(),
        $this->html_admin(), VERSION);

    $template = implode("", file($this->template));
    print preg_replace($search, $replace, $template);
    exit(0);
  }

  /**
   * Content for a page (JSON).  Exits.
   */
  function show_content($fullPath) {
      $entries = $this->list_folder($fullPath, $this->hideItems);
      $byInitial = $this->items_by_initial($entries);
      $groupedItems = $this->group_items($byInitial, $this->headingThreshold);
      $content = $this->html_folder($groupedItems);

      $result = array();
      $result['content'] = '<table width="100%">' . $content . "</table>";
      $result['cover'] = $this->html_cover();
      $result['breadcrumb'] = $this->html_breadcrumb();
      $result['options'] = $this->html_options();
      if (!empty($this->path->relative)) {
        $result['title'] = $this->homeName .": ". $this->path->relative;
      } else {
        $result['title'] = $this->homeName;
      }
      $result['error'] = Logger::pop();
      
      print $this->json_encode($result);
      exit(0);
  }

  /**
   * @return string Search field (HTML).
   */
  function html_searchfield() {
    if (!empty($this->searchDB) && is_readable($this->searchDB)) { 
      return '<input value="search" onFocus="enableSearch()" onBlur="disableSearch()" onKeyPress="invokeSearch(event)" 
         id=search size=14>';
    }
    return '';
  }

  /**
   * @return string Admin field (HTML).
   */
  function html_admin() {
    if ($this->allowLocal && !empty($this->searchDB)) {
      return ' <a class=feet href="javascript:buildDB()">rebuild search db</a>|';  
    } 
    return '';
  }


  /**
   * Search results (JSON).  Exits.
   *
   * @param Url url Url Object
   * @param Path path Path Object
   * @param string charset Character set
   */
  function show_search() {
    $needle = Util::strip($_GET['search']);
    $this->resolve_streamtype_and_shuffle();
    $searchresult = $this->search($needle);

    $content = "<ul class=searchresult>\n";
    foreach ($searchresult as $entry) {
      $entry = preg_replace("/[\r\n]/", "", $entry);
      $item = new Item($entry, $this->charset, false, $this->path, $this->url, $this->streamtype, $this->shuffle);

      if (is_dir($this->path->root . "/$entry")) {
        $content .= '<li>' . $item->dir_link() . '</li>\n';
      } else {
        $content .= '<li>' . $item->file_link() . '</li>\n';
      }
    }
    $result = array();
    $result['content'] = "$content</ul>";
    $result['numresults'] = count($searchresult);
    $result['breadcrumb'] = $this->html_linkedtitle() . ': search for "' . $needle . '"'
     . "<br> <a class=folder href=\"javascript:previousDir()\">[go back]</a>\n";
    $result['error'] = Logger::pop();
    $result['title'] = $this->homeName . ": search for \"" . $needle . "\"";
    print $this->json_encode($result);
    exit(0);
  }

  function show_streamtype() {
    $this->resolve_streamtype_and_shuffle();
    $result['streamtype'] = $this->streamtype;
    $result['shuffle'] = $this->shuffle;
    $result['error'] = Logger::pop();
    print $this->json_encode($result);
    exit(0);
  }

  /**
   * Rebuilds search database (JSON). Exits.
   */
  function show_builddb($rootPath) {
    $result = array();
    $this->build_searchdb($rootPath);
    $this->show_messages(); //Exits
  }

  /**
   * @return string The HTML that is required to display the flash player.
   */
  function html_flashplayer() {
    if (in_array(FLASH, $this->enabledPlay)) {
      return '<div id="player">JW FLV Player</div>
        <script type="text/javascript">
          jwObject().write(\'player\');
        </script>';
    }
  }

  /**
   * Simple JSON encoder.
   * @param array $array Array to encode
   * @return string JSON encoded content
   */
  function json_encode($array) {
    $json = array();
    $search = array('|"|', '|/|', "/\n/");
    $replace = array('\\"', '\\/', '\\n');
    foreach ($array as $key => $value) {
      $json[] = ' "' . preg_replace($search, $replace, $key)
              . '": "' . preg_replace($search, $replace, $value) . '"';
    }
    return "{\n" . implode($json, ",\n") . "\n}";
  }
  
  /**
   * Format music folder content as HTML.
   *
   * @return string Formatted HTML with folder content
   */
  function html_folder($groupedItems) {
    $output = "";
    if (count($groupedItems) > 0) {
      foreach ($groupedItems as $first => $group) {
        $entry = "";
        if (count($groupedItems) > 1) {
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
            $entry .= '<td class=cell>';
            
            $item = @ $group[$cell];
            if (is_object($item)) {
              $entry .= $item->show_link();
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

  /**
   * @return string Playback type selector (HTML)
   */
  function html_options() {
    $select = array();
    foreach ($this->enabledPlay as $list) {
      $select[$list] = "";
    }
    if (array_key_exists($this->streamtype, $select)) {
      $select[$this->streamtype] = 'SELECTED';
    }
    $pathEncoded = Util::path_encode($this->path->relative);
    $output = "<span class=feet>Play as <select name=streamtype onChange=\"setStreamtype('" . $pathEncoded . "', options[this.selectedIndex].value)\">";
    foreach ($select as $type => $checked) {
      switch ($type) {
        case SERVER:
          $display = "Server";
          break;
        case SLIM:
          $display = "Squeezebox";
          break;
        case XBMC:
          $display = "Xbox";
          break;
        default:
          $display = $type;
      }
      $output .= "<option value=$type $checked>$display</option>\n";
    }
    $output .= "</select></span>";
    $checked = "";
    if ($this->shuffle == 'true') {
      $checked = "CHECKED";
    } 
    $output .= "<span class=feet><input id=shuffle type=checkbox name=shuffle "
             . " onClick=\"setShuffle('" . $pathEncoded . "')\" $checked>shuffle</span>\n";
    return $output;
  }

  /**
   * @param array $entries array of entries
   * @param Url $url Url object
   * @param Path $path Path object
   * @param string $charset Character set
   * @param boolean $folderCovers Folder covers enabled
   * @return array Items grouped by initial ([initial][Item arrays])
   */
  function items_by_initial($entries) {
    natcasesort($entries);
    $group = array();
    foreach ($entries as $plainItem) {
        $item = new Item($plainItem, $this->charset, $this->folderCovers, 
          $this->path, $this->url, $this->streamtype, $this->shuffle);
        $index = " " . $item->sort_index();
        if (!isset($group[$index])) {
          $group[$index] = array();
        }
        $group[$index][] = $item;
    }
    ksort($group);
    return $group;
  }

  /**
   * Group $items by the first letter, with a minimum amount in each group.
   * @param array $itemsByInitial Items grouped by initial
   * @param integer $headingThreshold Max number of items in each group
   * @return array Items grouped in suitable groups ([from-toinitial][items])
   */
  function group_items($itemsByInitial, $headingThreshold) {
    $from = "";
    $i = 0;
    $mergedItems = array();
    $groupList = array();
    foreach ($itemsByInitial as $index => $itemGroup) {
      $mergedItems = array_merge($mergedItems, $itemGroup);
      if (count($mergedItems) >= $headingThreshold || (count($itemsByInitial) -1) == $i) {
        if ($from != "") {
          $index = "$from &ndash; $index"; 
        }
        $groupList[$index] = $mergedItems;
        $mergedItems = array();
        $from = "";
      } elseif ($from == "") {
        $from = $index;
      }
      $i++;
    }
    return $groupList;
  }

  /**
   * List folder content.
   * @return array Array with all allowed file and folder names
   */
  function list_folder($path, $hideItems) {
    $folderHandle = dir($path);
    $items = array();
    while (false !== ($item = $folderHandle->read())) {
      foreach ($hideItems as $hideItem) {
        if (preg_match($hideItem, $item)) continue 2; // to while loop
      }
      $fullPath = "$path/$item";
      if (is_dir($fullPath) || (is_file($fullPath) && $this->valid_suffix($item))) {
        $items[] = $item;
      }
    }
    $folderHandle->close();
    natcasesort($items);
    return $items;
  }

  /**
   * Resolves streamType and shuffle from $_GET and/or $_COOKIE.
   */
  function resolve_streamtype_and_shuffle() {
    $cookie = split(":", @ $_COOKIE['musicbrowser']);
    $setcookie = false;

    $streamType = @ $_GET['streamtype'];
    if (in_array($streamType, $this->enabledPlay)) {
      $setcookie = true;
    } else if (isset($cookie[0])) {
      $streamType = $cookie[0];
    }    
    if (!in_array($streamType, $this->enabledPlay)) {
      $streamType = $this->enabledPlay[0]; // Defaults to first play method in list
    }

    $shuffle = @ $_GET['shuffle'];
    if (strlen($shuffle) > 0) {
      $setcookie = true;
    } else if (isset($cookie[1])) {
      $shuffle = $cookie[1];
    }
    if ($shuffle != "true") {
      $shuffle = "false";
    }

    if ($setcookie) {
      setcookie('musicbrowser', "$streamType:$shuffle");
    }
    $this->streamtype = $streamType;
    $this->shuffle = $shuffle;
  }

  /**
   * @return string Formatted HTML with cover image (if any)
   */
  function html_cover() {
    $link = $this->path->cover_image();
    if (!empty($link)) {
      return "<a href=\"javascript:showCover('$link')\">"
           . "<img title=\"View enlarged cover\" alt=\"\" border=0 src=\"$link\" width={$this->thumbSize} "
           . "height={$this->thumbSize} align=left></a>";
    }
    return "";
  }

  function html_linkedtitle() {
    return "<a class=title href=\"javascript:changeDir('')\">{$this->homeName}</a>";
  }

  /**
   * @return string Formatted HTML with bread crumbs for folder
   */
  function html_breadcrumb() {
    $path = $this->path->relative;
    $parts = explode("/", trim($path, "/"));
    if ($parts[0] == '') {
      $parts = array();
    }
    $items = array($this->html_linkedtitle());
    $currentPath = $encodedPath = "";
    for ($i = 0; $i < count($parts); $i++) {
      $currentPath .= "/{$parts[$i]}";
      $encodedPath = Util::path_encode($currentPath);
      $displayItem = Util::convert_to_utf8($parts[$i], $this->charset);
      if ($i < count($parts) - 1) {
        $encodedPath = Util::js_url($encodedPath);
        $items[] = "<a class=path href=\"javascript:changeDir('$encodedPath')\">$displayItem</a>\n";
      } else {
        $items[] = "<span class=path>$displayItem</span>";
      }
    }
    $output = implode(" &nbsp;&raquo;&nbsp;", $items);

    # Output "play all"
    $playUrl = Util::play_url($encodedPath, $this->streamtype, $this->shuffle);
    $output .= "&nbsp;&nbsp;<a href=\"$playUrl\"><img
      src=\"play.gif\" border=0 title=\"Play all songs in this folder as " . $this->streamtype . "\"
      alt=\"Play all songs in this folder as " . $this->streamtype . "\"></a>";
    return $output;
  }

  /**
   * Checks if $entry has any of the $fileTypes.
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
    $fullPath = $this->path->root . "/$folder";
    $items = $this->list_folder($fullPath, $this->hideItems);
    foreach ($items as $item) {
      if (count($allItems) >= $this->maxPlaylistSize) {
        return $allItems;
      }
      if (is_file("$fullPath/$item")) {
        $allItems[] = "$folder/$item";
      } else {
        $allItems = $this->folder_items("$folder/$item", $allItems);
      }
    }
    return $allItems;
  }

  /**
   * Stream folder or file.
   */
  function stream($type, $shuffle) {
    if ($type == SLIM && $this->allowLocal) {
      $this->play_slimserver($this->path->relative);
      return;
    }
    
    if ($type == XBMC && $this->allowLocal) {
      $this->play_xbmc($this->path->relative);
      return;
    }
    
    $name = Util::pathinfo_basename($this->path->full);
    if (empty($name)) $name = "playlist";
    $items = array();
    
    if (is_dir($this->path->full)) {
      $items = $this->folder_items($this->path->relative, $items);
      if ($shuffle == 'true') {
        shuffle($items);
      } else {
        natcasesort($items);
      }
    } else {
      $items[] = $this->path->relative;
    }
    if (count($items) == 0) {
       Logger::log("No files to play in <b>$name</b>");
       return;
    }
    $entries = array();
    $withTimestamp = false;
    $withLength = false;
    if ($type == RSS) {
      $withTimestamp = true;
      $withLength = true;
    } 
    foreach ($items as $item) {
      $entries[] = $this->entry_info($item, $withTimestamp, $withLength);
    }

    switch ($type) {
      case RSS:
        $url = $this->url->full . "?path=" . Util::path_encode($this->path->relative);
        $coverImage = $this->path->cover_image();
        $image = "";
        if (!empty($coverImage)) {
          $image = $this->url->root . "/$coverImage";
        }
        $this->streamLib->playlist_rss($entries, $name, $url, $image, $this->charset);
        break;
      case M3U:
        $this->streamLib->playlist_m3u($entries, $name);
        break;
      case PLS:
        $this->streamLib->playlist_pls($entries, $name);
        break;
      case XSPF:
        $url = $this->url->full . "?path=" . Util::path_encode($this->path->relative);
        $coverImage = $this->path->cover_image();
        $image = "";
        if (!empty($coverImage)) {
          $image = $this->url->root . "/$coverImage";
        }
        $this->streamLib->playlist_xspf($entries, $name, $url, $image, $this->charset);
        break;
      case ASX:
      case FLASH:
        $this->streamLib->playlist_asx($entries, $name, $this->charset);
        break;
      case SERVER:
        if ($this->allowLocal) {
          $this->play_server($items);
        }
        break;
    }
  }

  /**
   * Info for entry in playlist.
   */
  function entry_info($item, $withTimestamp = false, $withLength = false) {
    $name = preg_replace("|\.[a-z0-9]{1,4}$|i", "", $item);
    $parts = array_reverse(preg_split("|/|", $name));
    $name = implode(" - ", $parts);
    if ($this->path->directFileAccess) {
      $url = $this->url->root . "/" . Util::path_encode($item, false);
    } else {
      $url = $this->url->full . "?path=" . Util::path_encode($item);
    }
    $entry = array('title' => $name, 'url' => $url);
    if ($withTimestamp) {
      $entry['timestamp'] = filectime($this->path->root . "/$item");
    }
    if ($withLength) {
      $entry['length'] = filesize($this->path->root . "/$item");
    }
    return $entry;
  }

  /**
   * Invokes an action on XBMC.
   * @see http://xbmc.org/wiki/?title=WebServerHTTP-API 
   */
  function invoke_xbmc($command, $parameter = "") {
    $url = $this->xbmcUrl . "/xbmcCmds/xbmcHttp?command=$command";
    if (strlen($parameter) > 0) $url .= "($parameter)";
    $data = $this->http_get($url);
    return $data;
  }

  /**
   * Play an item on XBMC. Exits.
   * @param string $item Item to play
   */
  function play_xbmc($item) {
    $data = $this->invoke_xbmc("Action", "13"); // ACTION_STOP
    $data = $this->invoke_xbmc("ClearPlayList", "0");
    $data = $this->invoke_xbmc("SetCurrentPlayList", "0");
    $parameter = $this->xbmcPath . "/" . Util::path_encode($item, false, true) . ";0;[music];1";
    $data = $this->invoke_xbmc("AddToPlayList", $parameter);
    if (preg_match("/Error/", $data) == 1) {
      Logger::log("Error reaching Xbmc: <b>" . $data . "</b>&nbsp; for URL " . $parameter);
    } else {
      Logger::log("Playing requested file(s) on Xbmc");
      $data = $this->invoke_xbmc("PlaylistNext");
    }
    $data = $this->invoke_xbmc("GetPlayListContents", "0");
    $this->show_messages(); //Exits
  }
  
  /**
   * Play item(s) on the local server.  Exits.  This server uses system() and might be VERY UNSAFE!
   * @param array $items Array of items to play
   */
  function play_server($items) {
    $args = "";
    foreach ($items as $item) {
      $args .= "\"" . $this->path->root . "/$item\" ";
    }
    system("{$this->serverPlayer} $args >/dev/null 2>/dev/null &", $ret);
    if ($ret === 0) {
      Logger::log("Playing requested file(s) on server");
    } else {
      Logger::log("Error playing file(s) on server.  Check the error log.");
    }
    $this->show_messages(); //Exits
  }

  /**
   * Play item on Slimserver.  Exits.
   * @param string $item Item to play
   */
  function play_slimserver($item) {
    $action = "/status_header.html?p0=playlist&p1=play&p2=" . urlencode("file://" . $this->path->full);
    $player = "&player=" . urlencode($this->slimserverPlayer);
    $url = $this->slimserverUrl . $action . $player;
    $data = $this->http_get($url);
    if (strlen($data) == 0) {
     Logger::log("Error reaching slimserver");
    } else {
     Logger::log("Playing requested file(s) on Squeezebox");
    }
    $this->show_messages(); //Exits
  }
  
  /**
   * Delivers messages as JSON and exits.
   */
  function show_messages() {
    $result = array();
    $msg = Logger::pop();
    $result['error'] = $msg;
    print $this->json_encode($result);
    exit(0);
  }

  /**
   * GETs an URL.
   * @param string $url The URL
   * @return Resulting content
   */
  function http_get($url) {
    if (!ini_get('allow_url_fopen')) {
      Logger::log("'allow_url_fopen' in php.ini is set to false");
      return;
    }
    if (!($fp = fopen($url, "r"))) {
      Logger::log("Could not open URL " . $url);
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
   * @param string $needle Needle(s) to search for (space separated AND search)
   * @return array List of results
   */
  function search($needle) {
    $needles = split(" ", strtolower($needle));
    $handle = false;
    $result = array();
    if (filesize($this->searchDB) == 0 || !is_readable($this->searchDB)) {
      Logger::log("Empty or unreadable search database ({$this->searchDB})");
      return $result;
    }
    $handle = fopen($this->searchDB, 'r');
    while (!feof($handle)) {
      $buffer = $this->decode_ncd(fgets($handle, 2048));
      $found = true;
      foreach ($needles as $needle) {
        if (stristr($buffer, $needle) === false) {
          $found = false;
          break;
        }
      }
      if ($found) {
        $result[] = $buffer;
      }
    }
    fclose($handle);
    return $result;
  }

  /**
   * MacOSX encodes filenames in UTF-8 on Normalization Form D (NFD), while
   * "everyone" else uses NFC. Normalizer is only available on PHP 5.3, or
   * with the PECL Internationalization extension ("intl").
   */
  function decode_ncd($str) {
    if (!normalizer_is_normalized($str)) {
      $str = normalizer_normalize($str);
    }
    return $str;
  }

  /**
   * Rebuilds the search database.
   * @param string $from Path to rebuild from
   * @return string Status message
   */
  function build_searchdb($from) {
    $handle = false;
    $message = false;
    $startTime = time();
    if (!$this->allowLocal) {
      Logger::log("Not authorized");
    } elseif (!$handle = fopen($this->searchDB, 'w')) {
      Logger::log("Unable to write to search database ({$this->searchDB})");
    } else {
      chdir($from);
      $dirs = array('.');
      while (NULL !== ($dir = array_pop($dirs))) {
        if ($dh = opendir($dir)) {
          while (false !== ($entry = readdir($dh))) {
            if ($entry == '.' || $entry == '..') continue;
            
            $path = "$dir/$entry";
            if (is_file($path) && !$this->valid_suffix($entry)) continue;

            if (fwrite($handle, substr("$path\n", 2)) === FALSE) {
                Logger::log("Cannot write \"$path\" to file ({$this->searchDB})");
                break 2;
            }
            if (is_dir($path)) $dirs[] = $path;
          }
          closedir($dh);
        }    
      }
      $seconds = time() - $startTime;
      Logger::log("Search DB built successfully in $seconds seconds.");
    }
    fclose($handle);
  }

}

class Logger {
  function log($msg) {
    if (!isset($_SESSION['message'])) {
      $_SESSION['message'] = $msg;
    } else {
      $_SESSION['message'] .= "<br>\n$msg";
    }
  }

  function pop() {
    if (isset($_SESSION['message'])) {
      $msg = $_SESSION['message'];
      unset($_SESSION['message']);
      return $msg;
    }
  }
}

class Path {

  var $root = NULL;     # e.g. /mnt/music
  var $relative = NULL; # e.g. Covenant/Stalker.mp3
  var $full= NULL;      # e.g. /mnt/music/Covenant/Stalker.mp3
  var $directFileAccess = false;

  /**
   * Try to resolve a safe path.
   */
  function Path($rootPath, $securePath) {
    
    if (!empty($rootPath) && !is_dir($rootPath)) {
      Logger::log("The \$config['path'] \"$rootPath\" isn't readable");
      return;
    } else if (empty($rootPath)) {
      $this->directFileAccess = true;
      $rootPath = getcwd();
    }
    
    $relPath = "";
    if (isset($_GET['path'])) {
      $getPath = stripslashes($_GET['path']);
      # Remove " (x22) and \ (x5c) and everything before ascii x20
      $getPath = Util::strip($getPath);
      $getPath = preg_replace(array("|%5c|", "|/\.\./|", "|/\.\.$|", "|^[/]*\.\.|"), 
                              array("", "", "", ""), $getPath);
      $getPath = trim($getPath, "/");
      if (is_readable("$rootPath/$getPath")) {
        $relPath = $getPath;
      } else {
        Logger::log("The path <i>$getPath</i> is not readable.");
      }
    }
    $fullPath = "$rootPath/$relPath";
    # Avoid funny paths
    $realFullPath = realpath($fullPath);
    $realRootPath = realpath($rootPath);
    
    if (($securePath && $realRootPath != substr($realFullPath, 0, strlen($realRootPath)))
         || !(is_dir($fullPath) || is_file($fullPath))) {
       $relPath = "";
       $fullPath = $rootPath;
    }
    $this->root = $rootPath;    # e.g. /mnt/music
    $this->relative = $relPath; # e.g. Covenant/Stalker.mp3
    $this->full = $fullPath;    # e.g. /mnt/music/Covenant/Stalker.mp3
  }

  function cover_image($addedPath = null) {
    $pathRelative = $this->relative;
    if ($addedPath) {
      $pathRelative .= "/$addedPath";
    }
    $covers = array("cover.jpg", "Cover.jpg", "folder.jpg", "Folder.jpg", "cover.gif", "Cover.gif",
                  "folder.gif", "Folder.gif");
    foreach ($covers as $cover) {
      if (is_readable($this->root . "/$pathRelative/$cover")) {
        $coverPath = Util::path_encode("$pathRelative/$cover", false);
        if ($this->directFileAccess) {
          return $coverPath;
        } else {
          return "index.php?path=" . $coverPath;
        }
      }
    }
    return "";
  }

}

class Url {

  var $root = NULL;     # e.g. http://mysite.com
  var $relative = NULL; # e.g. musicbrowser
  var $full = NULL;     # e.g. http://mysite.com/musicbrowser
  
  /**
   * Resolve the current URL into $root, $relative and $full.
   * @param string $rootUrl The input URL
   */
  function Url($rootUrl) {
    if (empty($rootUrl)) {
      $folder = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
      $this->root = Url::protocol() . $_SERVER['HTTP_HOST'] . $folder;
    } else {
      if (!preg_match('#^https?:/(/[a-z0-9]+[a-z0-9:@\.-]+)+#i', $rootUrl)) {
        Logger::log("The \$config['url'] \"{$this->root}\" is invalid");
        return;
      }
      $this->root = trim($rootUrl, '/');
    }
    $this->relative = Util::pathinfo_basename($_SERVER['SCRIPT_NAME']);
    $this->full = $this->root . "/" . $this->relative;
  }

  /**
   * @return string Current url scheme
   */
  function protocol() {
    if (isset($_SERVER["HTTPS"])) {
        return "https://";
    }
    return "http://";
  }

}

/**
 * A file or folder item.
 */
class Item {
  var $item;
  var $urlPath;
  var $charset;
  var $folderCovers;
  var $path;
  var $url;

  function Item($item, $charset, $folderCovers, $path, $url, $streamtype, $shuffle) {
    $this->item = $item;  
    $this->charset = $charset;
    $this->folderCovers = $folderCovers;
    $this->path = $path;
    $this->url = $url;
    $this->streamtype = $streamtype;
    $this->shuffle = $shuffle;
  } 
  
  function url_path() {
    if (empty($this->urlPath)) {
      $this->urlPath = Util::path_encode($this->path->relative . "/" . $this->item);
    }
    return $this->urlPath;
  }
  
  function sort_index() {
    $firstChars = mb_substr($this->item, 0, 2, $this->charset);
    return  mb_convert_case($firstChars, MB_CASE_TITLE, $this->charset);
  }
  
  function js_url_path() {
    return Util::js_url($this->url_path());    
  }
  
  function display_item() {
    $displayItem = Util::word_wrap($this->item);
    $displayItem = Util::convert_to_utf8($displayItem, $this->charset);
    return $displayItem;
  }
  
  function show_link() {
    if (empty($this->item)) {
      return "&nbsp;";
    } elseif (is_dir($this->path->full . "/" . $this->item)) {
      return $this->dir_link();
    } else {
      return $this->file_link();
    }
  }
  
  function dir_link() {
    $image = $this->show_folder_cover($this->item);
    $link = Util::play_url($this->url_path(), $this->streamtype, $this->shuffle);
    $jsurl = $this->js_url_path();
    $item = $this->display_item();
    
    return "$image<a title=\"Play files in this folder\" href=\"$link\">"
      . "<img border=0 alt=\"|&gt; \" src=\"play.gif\"></a>\n"
      . "<a class=folder href=\"javascript:changeDir('$jsurl')\">$item</a>\n";
  }
  
  function file_link() {
    $download = $this->direct_link($this->path->relative . "/" . $this->item);
    $link = Util::play_url($this->url_path(), $this->streamtype, $this->shuffle);
    $item = $this->display_item();
    
    return "<a href=\"$download\">"
      . "<img src=\"download.gif\" border=0 title=\"Download this song\" alt=\"[Download]\"></a>\n"
      . "<a class=file title=\"Play this song\" href=\"$link\">$item</a>\n";
  }
  
  function direct_link($urlPath) {
     if ($this->path->directFileAccess) {
       return Util::path_encode($urlPath, false);
     } 
     return $this->url->relative . "?path=" . Util::path_encode($urlPath);
  }
  
  function show_folder_cover($addedPath) {
    $image = "";
    if ($this->folderCovers) {
      $coverImage = $this->path->cover_image($addedPath);
      if (!empty($coverImage)) {
        $jsUrlPath = Util::js_url(Util::path_encode($this->path->relative . "/$addedPath"));
        $image = "<a href=\"javascript:changeDir('" . $this->js_url_path() 
               . "')\"><img src=\"$coverImage\" border=0 width=100 height=100 alt=\"\"></a><br>";
      }
    }
    return $image;
  }  
}

/**
 * Common static utility functions.
 */
class Util {
  /**
   * Need to encode url entities twice in javascript calls.
   */
  function js_url($url) {
    return preg_replace("/%([0-9a-f]{2})/i", "%25\\1", $url);
  }
  
  /**
   * Encode a fairly readable path for the URL.
   */
  function path_encode($path, $encodeSpace = true, $utf8Encode = false) {
     $search = array("|^%2F|", "|%2F|");
     $replace = array("", "/");
     if ($encodeSpace) {
       $search[] = "|%20|";
       $replace[] = "+";
     }
     if ($utf8Encode) $path = utf8_encode($path);
     return preg_replace($search, $replace, rawurlencode($path)); 
  }
  
  function play_url($urlPath, $streamtype, $shuffle) {
    if ($streamtype == FLASH || $streamtype == SLIM || $streamtype == SERVER || $streamtype == XBMC) {
       $urlPath = Util::js_url($urlPath);
       return "javascript:play('$urlPath')";
    }
    return "index.php?path=" . $urlPath . "&amp;shuffle=" . $shuffle . "&amp;stream=" . $streamtype;
  }

  function strip($str) {
    return preg_replace('/[^\x20-\x21\x23-\x5b\x5d-\xff]/', "", $str);
  }

  function word_wrap($item) {
    return wordwrap(preg_replace("/_/", " ", $item), 40, " ", true);
  }

  /**
   * The Flash MP3 player can only handle utf-8.
   */
  function convert_to_utf8($entry, $fromCharset) {
    if ($fromCharset != "utf-8") {
      $entry = utf8_encode($entry);
    }
    return $entry;
  }

  /**
   * @param string $file A file path
   * @return The last part of a path
   */
  function pathinfo_basename($file) {
     return array_pop(explode("/", $file));
  }
}


class StreamLib {

  /**
   * @param array $entries Array of arrays with keys moreinfo, url, starttime, duration, title, author & copyright
   * @param string $name Stream name
   */
  function playlist_asx($entries, $name = "playlist", $convertFromCharset = "iso-8859-1") {
     $output = "<asx version=\"3.0\">\r\n";
     $output .= "<param name=\"encoding\" value=\"utf-8\" />\r\n";
     foreach ($entries as $entry) {
        $title = Util::convert_to_utf8($entry['title'], $convertFromCharset);
        $output .= "  <entry>\r\n";
        $output .= "    <ref href=\"{$entry['url']}\" />\r\n";
        if (isset($entry['moreinfo']))  $output .= "    <moreinfo href=\"{$entry['moreinfo']}\" />\r\n";
        if (isset($entry['starttime'])) $output .= "    <starttime value=\"{$entry['starttime']}\" />\r\n";
        if (isset($entry['duration']))  $output .= "    <duration value=\"{$entry['duration']}\" />\r\n";
        if (isset($entry['title']))     $output .= "    <title>$title</title>\r\n";
        if (isset($entry['author']))    $output .= "    <author>{$entry['author']}</author>\r\n";
        if (isset($entry['copyright'])) $output .= "    <copyright>{$entry['copyright']}</copyright>\r\n";
        $output .= "  </entry>\r\n";
     }
     $output .= "</asx>\r\n";
     
     $this->stream_content($output, "$name.asx", "audio/x-ms-asf");
  }


  /**
   * @param array $entries Array of arrays with keys url, title
   * @param string $name Stream name
   */
  function playlist_pls($entries, $name = "playlist") {
     $output = "[playlist]\r\n";
     $output .= "X-Gnome-Title=$name\r\n";
     $output .= "NumberOfEntries=" . count($entries) . "\r\n";
     $counter = 1;
     foreach ($entries as $entry) {
        $output .= "File$counter={$entry['url']}\r\n"
                 . "Title$counter={$entry['title']}\r\n"
                 . "Length$counter=-1\r\n";
        $counter++;
     }
     
     $output .= "Version=2\r\n";
     
     $this->stream_content($output, "$name.pls", "audio/x-scpls");
  }

  /**
   * @param array $entries Array of arrays with keys url, title
   * @param string $name Stream name
   */
  function playlist_m3u($entries, $name = "playlist") {
    $output = "#EXTM3U\r\n";
    foreach ($entries as $entry) {
      $output .= "#EXTINF:0, {$entry['title']}\r\n"
               . "{$entry['url']}\r\n";
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
    $name = Util::convert_to_utf8(htmlspecialchars($name), $charset);
    $output = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n"
            . "<rss xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\" xmlns:atom=\"http://www.w3.org/2005/Atom\" version=\"2.0\">\r\n"
            . "<channel><title>$name</title><link>$link</link>\r\n"
            . "  <description>$name</description>\r\n"
            . "  <atom:link href=\"$link&amp;stream=rss\" rel=\"self\" type=\"application/rss+xml\" />\r\n";
    if (!empty($image)) {
      $output .= "  <image><url>$image</url><title>$name</title><link>$link</link></image>\r\n";
    }
    foreach ($entries as $entry) {
      $date = date('r', $entry['timestamp']);
      $url = htmlspecialchars($entry['url']);
      $title = Util::convert_to_utf8(htmlspecialchars($entry['title']), $charset);
      $output .= "  <item><title>$title</title>\r\n"
               . "    <enclosure url=\"$url\" length=\"{$entry['length']}\" type=\"audio/mpeg\" />\r\n"
               . "    <guid>$url</guid>\r\n"
               . "    <pubDate>$date</pubDate>\r\n"
               . "  </item>\r\n";
    }
    $output .= "</channel></rss>\r\n";
    $this->stream_content($output, "$name.rss", "application/xml", "inline");
  }

  /**
   * @param array $entries Array of arrays with keys url, title, timestamp
   * @param string $name Stream name
   * @param string $link The link to this rss
   * @param string $image Album cover (optional)
   */
  function playlist_xspf($entries, $name = "playlist", $link, $image = "", $charset = "iso-8859-1") {
    $link = htmlspecialchars($link);
    $name = Util::convert_to_utf8(htmlspecialchars($name), $charset);
    $output = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n"
            . "<playlist version=\"1\" xmlns=\"http://xspf.org/ns/0/\">\r\n"
            . "  <title>$name</title>\r\n"
            . "  <trackList>\r\n";

    foreach ($entries as $entry) {
      $url = htmlspecialchars($entry['url']);
      $title = Util::convert_to_utf8(htmlspecialchars($entry['title']), $charset);
      $output .= "    <track>\r\n"
               . "      <location>$url</location>\r\n"
               . "      <title>$title</title>\r\n";
 	  if (!empty($image)) {
 		$output .= "      <image>$image</image>\r\n";
 	  }
 	  $output .= "    </track>\r\n";
    }
    $output .= "  </trackList>\r\n"
             . "</playlist>\r\n";
    $this->stream_content($output, "$name.xspf", "application/xspf+xml", "inline");
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