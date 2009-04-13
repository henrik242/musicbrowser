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
define('VERSION', '0.20');

class MusicBrowser {
  
  var $columns = 5;
  var $infoMessage = '';
  var $headingThreshold = 14;
  var $thumbSize = 100;
  var $allowLocal = false;
  var $homeName, $streamLib, $fileTypes, $template, $charset, $searchDB;
  var $serverPlayer, $maxPlaylistSize, $slimserverUrl, $slimserverUrlSuffix;
  var $enabledPlay = array();
  var $directFileAccess = false;
  
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
    $this->streamLib = new StreamLib();
  }

  /**
   * Resolves the configuration.
   * @param array $config
   */
  function resolve_config($config) {
    $this->resolve_url($config['url']); // need to resolve url before path
  
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
        default:
          $this->$key = $value;
          break;
      }
    }
    $this->resolve_path($config['path']);
    
    if (!$this->allowLocal || strlen($this->slimserverUrl) == 0) $this->disablePlayMethod(SLIM);
    if (!$this->allowLocal || strlen($this->serverPlayer) == 0) $this->disablePlayMethod(SERVER);
    if (!$this->allowLocal || strlen($this->xbmcUrl) == 0) $this->disablePlayMethod(XBMC);
  }

  /**
   * Disable an allowed play method.
   * @param string $disable Play method to disable
   */
  function disablePlayMethod($disable) {
    foreach ($this->enabledPlay as $key => $var) {
      if ($var == $disable) {
        unset($this->enabledPlay[$key]);
        break;
      }
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
      $this->show_builddb(); // Exits
    }    
    if (isset($_GET['verify'])) {
      $this->show_verify(); // Exits
    }
    
    if ((is_dir(PATH_FULL) || is_file(PATH_FULL)) && isset($_GET['stream'])) {
      # If streaming is requested, do it
      $this->stream($_GET['stream'], @$_GET['shuffle']);
      exit(0);
    } elseif (is_file(PATH_FULL)) {
      # If the path is a file, download it
      $this->streamLib->stream_file_auto(PATH_FULL);
      exit(0);
    } 

    # Set stream/play type from $_GET or $_SESSION
    $this->set_stream_type();

    if (isset($_GET['content'])) {
      $this->show_content(); // Exits
    }
    
    if (isset($_GET['message'])) {
      $this->add_message($_GET['message']);
    }

    $search = array("/%folder%/", "/%flash_player%/", "/%searchfield%/", "/%admin%/", "/%version%/");
    $replace = array(addslashes(PATH_RELATIVE), $this->html_flashplayer(), $this->html_searchfield(), 
        $this->html_admin(), VERSION);

    $template = implode("", file($this->template));
    print preg_replace($search, $replace, $template);
    exit(0);
  }

  /**
   * Content for a page (JSON).  Exits.
   */
  function show_content() {
      $items = $this->list_folder(PATH_FULL);
      $content = $this->html_folder($items);

      $result = array();
      $result['content'] = '<table width="100%">' . $content . "</table>";
      $result['cover'] = $this->html_cover();
      $result['breadcrumb'] = $this->html_breadcrumb();
      $result['options'] = $this->html_options();
      $result['error'] = $this->pop_messages();
      
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
   * Initialize SHUFFLE and STREAMTYPE defines in cases where they're not defined (i.e. search).
   * @see show_search()
   */
  function init_shuffle_and_streamtype() {
    if (!defined('SHUFFLE')) {
      if (isset($_SESSION['shuffle'])) {
        define('SHUFFLE', $_SESSION['shuffle']);
      } else {
        define('SHUFFLE', "false");
      }
    }
    if (!defined('STREAMTYPE')) {
      if (isset($_SESSION['streamtype'])) {
        define('STREAMTYPE', $_SESSION['streamtype']);
      } else {
        define('STREAMTYPE', FLASH);
      }
    }
  }

  /**
   * Search results (JSON).  Exits.
   */
  function show_search() {
    $needle = preg_replace("/[^a-zA-Z0-9 +-]/", "", $_GET['search']);
    $this->init_shuffle_and_streamtype();
    $searchresult = $this->search($needle);

    $content = "<ul class=searchresult>\n";
    foreach ($searchresult as $entry) {
      $entry = preg_replace("/[\r\n]/", "", $entry);
      $item = new Item($entry, $this->charset, $this->directFileAccess, false);

      if (is_dir(PATH_ROOT . "/$entry")) {
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
    $result['error'] = $this->pop_messages();
    print $this->json_encode($result);
    exit(0);
  }

  /**
   * Rebuilds search database (JSON). Exits.
   */
  function show_builddb() {
      $result = array();
      $result['error'] = $this->build_searchdb(PATH_ROOT);
      print $this->json_encode($result);
      exit(0);
  }

  /**
   * Displays the already verified path validity (JSON).  Exits.
   */
  function show_verify() {
      $result = array();
      if (!empty($this->infoMessage)) {
        $result['error'] = $this->pop_messages();
      }
      print $this->json_encode($result);
      exit(0);
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
  function html_folder($items) {

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
    if (array_key_exists(STREAMTYPE, $select)) {
      $select[STREAMTYPE] = 'SELECTED';
    }
    $pathEncoded = Util::path_encode(PATH_RELATIVE);
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
    if (SHUFFLE == 'true') {
      $checked = "CHECKED";
    } 
    $output .= "<span class=feet><input id=shuffle type=checkbox name=shuffle "
             . " onClick=\"setShuffle('" . $pathEncoded . "')\" $checked>shuffle</span>\n";
    return $output;
  }

  /**
   * Group $items by the first letter, with a minimum amount in each group.
   * @see $this->headingThreshold
   */
  function group_items($items) {
    natcasesort($items);
    $group = array();
    foreach ($items as $plainItem) {
        $item = new Item($plainItem, $this->charset, $this->directFileAccess, $this->folderCovers);
        $index = $item->sort_index();
        @ $group[" $index"][] = $item;
    }
    
    $mergedItems = array();
    $from = "";
    $i = 0;
    ksort($group);
    foreach ($group as $index => $items) {
      $mergedItems = array_merge($mergedItems, $items);
      if (count($mergedItems) >= $this->headingThreshold || (count($group) -1) == $i) {
        if ($from != "") {
          $index = "$from &ndash; $index"; 
        }
        $groupList["$index"] = $mergedItems;
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
  function list_folder($path) {
    $folderHandle = dir($path);
    $items = array();
    while (false !== ($item = $folderHandle->read())) {
      foreach ($this->hideItems as $hideItem) {
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
   * Fetches stream/play type from $_POST or $_COOKIE.
   * @return string streamtype
   */
  function set_stream_type() {
    $streamType = $shuffle = "";
    
    if (isset($_GET['streamtype']) and strlen($_GET['streamtype']) > 0 
        and in_array($_GET['streamtype'], $this->enabledPlay)) {
      $streamType = $_GET['streamtype'];
      $_SESSION['streamtype'] = $streamType;
    } else if (isset($_SESSION['streamtype'])) {
      $streamType = $_SESSION['streamtype'];
    }    
    if (!in_array($streamType, $this->enabledPlay)) {
      $streamType = $this->enabledPlay[0];
    }
    
    if (isset($_GET['shuffle']) and strlen($_GET['shuffle']) > 0) {
      $shuffle = $_GET['shuffle'];
      $_SESSION['shuffle'] = $shuffle;
    } else if (isset($_SESSION['shuffle'])) {
      $shuffle = $_SESSION['shuffle'];
    }
    if ($shuffle != "true") {
      $shuffle = "false";
    }
    
    define('STREAMTYPE', $streamType);
    define('SHUFFLE', $shuffle);
  }

  /**
   * @return string Formatted HTML with cover image (if any)
   */
  function html_cover($pathRelative = PATH_RELATIVE) {
    $link = Util::cover_image($pathRelative, $this->directFileAccess);
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
    $path = PATH_RELATIVE;
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
    $output .= "&nbsp;&nbsp;<a href=\"" . Util::play_url($encodedPath) . "\"><img 
      src=\"play.gif\" border=0 title=\"Play all songs in this folder as " . STREAMTYPE . "\"
      alt=\"Play all songs in this folder as " . STREAMTYPE . "\"></a>";
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
    $fullPath = PATH_ROOT . "/$folder";
    $items = $this->list_folder($fullPath);
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
      $this->play_slimserver(PATH_RELATIVE);
      return;
    }
    
    if ($type == XBMC && $this->allowLocal) {
      $this->play_xbmc(PATH_RELATIVE);
      return;
    }
    
    $name = $this->pathinfo_basename(PATH_FULL);
    if (empty($name)) $name = "playlist";
    $items = array();
    
    if (is_dir(PATH_FULL)) {
      $items = $this->folder_items(PATH_RELATIVE, $items);
      if ($shuffle == 'true') {
        shuffle($items);
      } else {
        natcasesort($items);
      }
    } else {
      $items[] = PATH_RELATIVE;
    }
    if (count($items) == 0) {
       $this->add_message("No files to play in <b>$name</b>");
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
        $url = URL_FULL . "?path=" . Util::path_encode(PATH_RELATIVE);
        $coverImage = Util::cover_image(PATH_RELATIVE, $this->directFileAccess);
        $image = "";
        if (!empty($coverImage)) {
          $image = URL_ROOT . "/$coverImage";
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
 		$url = URL_FULL . "?path=" . Util::path_encode(PATH_RELATIVE);
 		$coverImage = Util::cover_image(PATH_RELATIVE, $this->directFileAccess);
 		$image = "";
 		if (!empty($coverImage)) {
 		  $image = URL_ROOT . "/$coverImage";
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
    if ($this->directFileAccess) {
      $url = URL_ROOT . "/" . Util::path_encode($item, false);
    } else {
      $url = URL_FULL . "?path=" . Util::path_encode($item);
    }
    $entry = array('title' => $name, 'url' => $url);
    if ($withTimestamp) {
      $entry['timestamp'] = filectime(PATH_ROOT . "/$item");
    }
    if ($withLength) {
      $entry['length'] = filesize(PATH_ROOT . "/$item");
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
      $this->add_message("Error reaching Xbmc: <b>" . $data . "</b>&nbsp; for URL " . $parameter);
    } else {
      $this->add_message("Playing requested file(s) on Xbmc");
      $data = $this->invoke_xbmc("PlaylistNext");
    }
    $data = $this->invoke_xbmc("GetPlayListContents", "0");
    $this->reload_page(); //exits
  }
  
  /**
   * Play item(s) on the local server.  Exits.  This server uses system() and might be VERY UNSAFE!
   * @param array $items Array of items to play
   */
  function play_server($items) {
    $args = "";
    foreach ($items as $item) {
      $args .= "\"" . PATH_ROOT . "/$item\" ";
    }
    system("{$this->serverPlayer} $args >/dev/null 2>/dev/null &", $ret);
    if ($ret === 0) {
      $this->add_message("Playing requested file(s) on server");
    } else {
      $this->add_message("Error playing file(s) on server.  Check the error log.");
    }
    $this->reload_page(); //exits
  }

  /**
   * Play item on Slimserver.  Exits.
   * @param string $item Item to play
   */
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
   * Redirect to current folder page.  Exits.
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
    $url = URL_FULL . "?path=" . Util::path_encode($path);
    $_SESSION['message'] = $this->infoMessage;
    header("Location: $url");
    exit(0);
  }

  /**
   * GETs an URL.
   * @param string $url The URL
   * @return Resulting content
   */
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
    $_SESSION['message'] = $this->infoMessage;
  }

  /**
   * @return string Clears and returns the message stack
   */
  function pop_messages() {
    $_SESSION['message'] = "";
    return $this->infoMessage;
  }

  /**
   * Try to resolve a safe path.
   */
  function resolve_path($rootPath) {
    if (empty($rootPath)) $this->directFileAccess = true;
    if (!empty($rootPath) && !is_dir($rootPath)) {
      $this->show_fatal_error("The \$config['path'] \"$rootPath\" isn't readable");
    }
    if (empty($rootPath)) {
       $rootPath = getcwd();
    }
    
    $relPath = "";
    if (isset($_GET['path'])) {
      $getPath = stripslashes($_GET['path']);
      # Remove " (x22) and \ (x5c) and everything before ascii x20
      $getPath = preg_replace('/[^\x20-\x21\x23-\x5b\x5d-\xff]/', "", $getPath);
      $getPath = preg_replace(array("|%5c|", "|/\.\./|", "|/\.\.$|", "|^[/]*\.\.|"), 
                              array("", "", "", ""), $getPath);
                                    
      if (is_readable("$rootPath/$getPath")) {
        $relPath = $getPath;
      } else {
        $this->add_message("The path <i>$getPath</i> is not readable.");
        //$this->reload_page(); // exits
      }
    }
    $fullPath = "$rootPath/$relPath";
    # Avoid funny paths
    $realFullPath = realpath($fullPath);
    $realRootPath = realpath($rootPath);
    
    if (($this->securePath && $realRootPath != substr($realFullPath, 0, strlen($realRootPath)))
         || !(is_dir($fullPath) || is_file($fullPath))) {
       $relPath = "";
       $fullPath = $rootPath;
    }
    define('PATH_ROOT', $rootPath);    # e.g. /mnt/music
    define('PATH_RELATIVE', $relPath); # e.g. Covenant/Stalker.mp3
    define('PATH_FULL', $fullPath);    # e.g. /mnt/music/Covenant/Stalker.mp3
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


  /**
   * Resolve the current URL into URL_ROOT, URL_RELATIVE and URL_FULL.
   * @param string $rootUrl The input URL
   */
  function resolve_url($rootUrl) {

    if (empty($rootUrl)) {
      $folder = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME);
      $root = $this->protocol() . $_SERVER['HTTP_HOST'] . $folder;
    } else {
      $root = trim($rootUrl, '/');
      if (!preg_match('#^https?:/(/[a-z0-9]+[a-z0-9:@-\.]+)+$#i', $root)) {
        $this->show_fatal_error("The \$config['url'] \"{$root}\" is invalid");
      }
    } 
    $relative = $this->pathinfo_basename($_SERVER['SCRIPT_NAME']);
    define('URL_ROOT', $root);             # e.g. http://mysite.com
    define('URL_RELATIVE', $relative);     # e.g. musicbrowser
    define('URL_FULL', "$root/$relative"); # e.g. http://mysite.com/musicbrowser
  }

  /**
   * @param string $file A file path
   * @return The last part of a path
   */
  function pathinfo_basename($file) {
     return array_pop(explode("/", $file));
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
        $this->add_message("Empty or unreadable search database ({$this->searchDB})");
        return $result;
    }
    $handle = fopen($this->searchDB, 'r');
    while (!feof($handle)) {
        $buffer = fgets($handle, 2048);
        $bufferLower = strtolower($buffer);
        $found = true;
        foreach ($needles as $needle) {
          if (strpos($bufferLower, $needle) === false) {
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
   * Rebuilds the search database.
   * @param string $from Path to rebuild from
   * @return string Status message
   */
  function build_searchdb($from) {
    $handle = false;
    $message = false;
    $startTime = time();
    if (!$this->allowLocal) {
      $message = "Not authorized";      
    } elseif (!$handle = fopen($this->searchDB, 'w')) {
      $message = "Unable to write to search database ({$this->searchDB})";
    } else {
      chdir($from);
      $dirs = array('.');  
      while (NULL !== ($dir = array_pop($dirs))) {  
        if ($dh = opendir($dir)) {
          while (false !== ($entry = readdir($dh))) {      
            if ($entry == '.' || $entry == '..') continue;        
            
            $path = "$dir/$entry";
            if (is_file($path) && !$this->valid_suffix($entry)) continue;
            
            if (fwrite($handle, substr("$path\n",2)) === FALSE) {
                $message = "Cannot write \"$path\" to file ({$this->searchDB})";
                break 2;
            }
            if (is_dir($path)) $dirs[] = $path;        
          }      
          closedir($dh);      
        }    
      }
      $seconds = time() - $startTime;
      $message = "Search DB built successfully in $seconds seconds.";
    }
    fclose($handle);
    return $message; 
  }

}

/**
 * A file or folder item.
 */
class Item {
  var $item;
  var $urlPath;
  var $charset;
  var $directFileAccess;
  var $folderCovers;
  
  function Item($item, $charset, $directFileAccess, $folderCovers) {
    $this->item = $item;  
    $this->charset = $charset;
    $this->directFileAccess = $directFileAccess;
    $this->folderCovers = $folderCovers;
  } 
  
  function url_path() {
    if (empty($this->urlPath)) {
      $this->urlPath = Util::path_encode(PATH_RELATIVE . "/" . $this->item);    
    }
    return $this->urlPath;
  }
  
  function sort_index() {
    //return date("Y-m-d", filectime(PATH_FULL . "/" . $this->item));
    return strtoupper($this->item{0});
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
    } elseif (is_dir(PATH_FULL . "/" . $this->item)) {
      return $this->dir_link();
    } else {
      return $this->file_link();
    }
  }
  
  function dir_link() {
    $image = $this->show_folder_cover(PATH_RELATIVE . "/" . $this->item);
    return "$image<a title=\"Play files in this folder\" href=\"" . Util::play_url($this->url_path()) 
      . "\"><img border=0 alt=\"|&gt; \" src=\"play.gif\"></a>\n"
      . "<a class=folder href=\"javascript:changeDir('" . $this->js_url_path() . "')\">" 
      . $this->display_item() ."</a>\n";
  }
  
  function file_link() {
    return "<a href=\"" . $this->direct_link(PATH_RELATIVE . "/" . $this->item) . "\">"
      . "<img src=\"download.gif\" border=0 title=\"Download this song\" alt=\"[Download]\"></a>\n"
      . "<a class=file title=\"Play this song\" href=\"" . Util::play_url($this->url_path()) 
      . "\">" . $this->display_item() . "</a>\n";
  }
  
  function direct_link($urlPath) {
     if ($this->directFileAccess) {
       return Util::path_encode($urlPath, false);
     } 
     return URL_RELATIVE . "?path=" . Util::path_encode($urlPath);
  }
  
  function show_folder_cover($pathRelative) {
    $image = "";
    if ($this->folderCovers) {
      $coverImage = Util::cover_image($pathRelative, $this->directFileAccess);
      if (!empty($coverImage)) {
        $jsUrlPath = Util::js_url(Util::path_encode($pathRelative));
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
  
  function play_url($urlPath) {
    if (STREAMTYPE == FLASH) {
       $urlPath = Util::js_url($urlPath);
       return "javascript:jwPlay('$urlPath', " . SHUFFLE . ")";
    }
    return URL_RELATIVE . "?path=" . $urlPath . "&amp;shuffle=" . SHUFFLE . "&amp;stream=" . STREAMTYPE;
  }
  
  function cover_image($pathRelative, $directFileAccess) {
    $covers = array("cover.jpg", "Cover.jpg", "folder.jpg", "Folder.jpg", "cover.gif", "Cover.gif",
                  "folder.gif", "Folder.gif");
    foreach ($covers as $cover) {
      if (is_readable(PATH_ROOT . "/$pathRelative/$cover")) {
        $pathEncoded = Util::path_encode("$pathRelative/$cover", false);
        if ($directFileAccess) {
          return $pathEncoded;
        } else {
          return URL_RELATIVE . "?path=" . $pathEncoded;
        }
      }
    }
    return "";
  }
  
  function word_wrap($item) {
    if (strlen($item) > 40) {
      $item = preg_replace("/_/", " ", $item);
      $pos = strpos($item, " ");
      if ($pos > 40 || !$pos) {
        $item = substr($item, 0, 30) . " " . Util::word_wrap(substr($item, 30));
      }
    }
    return $item;
  }

  /**
   * The Flash MP3 player can only handle utf-8.
   */
  function convert_to_utf8($entry, $fromCharset) {
    if (!function_exists('utf8_encode')) {
      return $entry; // Won't look very nice, but at least it runs
    }
    if ($fromCharset != "utf-8") {
      $entry = utf8_encode($entry);
    }
    return $entry;
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