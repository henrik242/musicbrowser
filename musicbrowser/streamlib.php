<?php

/**
 *   $Id: streamlib.php,v 1.17 2007-11-12 09:27:34 mingoto Exp $
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
 *   Copyright 2006, 2007 Henrik Brautaset Aronsen
 */

class MusicBrowser {
  
  var $columns = 5;   # minimum 3
  var $infoMessage = "";
  var $headingThreshold = 15;
  var $homeName, $path, $url, $streamLib;
  var $suffixes, $streamType, $template;
  var $player;
  var $thumbSize = 150;
  var $maxPlaylistSize, $slimserverUrl, $slimserverUrlSuffix;
  var $allowLocal = false;
  var $playlists = array();
  
  /**
   * @param array $config Assosciative array with configuration
   *                      Keys: url, path, fileTypes, template, homeName
   */  
  function MusicBrowser($config) {
    #ini_set("error_reporting", E_ALL);
    ini_set("display_errors", 0);

    if (!is_readable($config['template'])) {
      $this->fatal_error("The \$config['template'] \"{$config['template']}\" isn't readable");
    }
    
    $this->path = $this->resolve_path($config['path']);
    $this->suffixes = $config['fileTypes'];
    $this->homeName = $config['homeName'];
    $this->template = $config['template'];
    $this->player = $config['player'];
    $this->slimserverUrl = $config['slimserverUrl'];
    $this->slimserverUrlSuffix = $config['slimserverUrlSuffix'];
    $this->maxPlaylistSize = $config['maxPlaylistSize'];
    $this->url = $this->resolve_url($config['url']);
    if ($config['enableM3u']) $this->playlists[] = "m3u";
    if ($config['enableAsx']) $this->playlists[] = "asx";
    if ($config['enablePls']) $this->playlists[] = "pls";
    
    foreach ($config['allowLocal'] as $host) {
      if (empty($host)) continue;
      if (preg_match($host, gethostbyaddr($_SERVER['REMOTE_ADDR'])) > 0
          || preg_match($host, gethostbyname($_SERVER['REMOTE_ADDR'])) > 0) {
        $this->allowLocal = true;
      }
    }
    $this->streamLib = new StreamLib();
  }

  function fatal_error($message) {
      echo "<html><body text=red>ERROR: $message</body></html>";
      exit(0);
  }
  
  /**
   * Display requested page.
   */
  function show_page() {
    $fullPath = $this->path['full'];

    if ((is_dir($fullPath) || is_file($fullPath)) && isset($_GET['stream'])) {
      # If streaming is requested, do it
      $this->stream_all($_GET['stream']);
      $fullPath = $this->path['full'];
    } elseif (is_file($fullPath)) {
      # If the path is a file, download it
      $this->streamLib->stream_file_auto($fullPath);
      exit(0);
    } 

    # List of files and folders
    $entries = $this->list_folder($fullPath);

    # Set stream type as pls or m3u from $_POST or $_COOKIE
    $this->streamType = $this->set_stream_type();

    # get all content for template
    $coverImage = $this->show_cover();
    $topPath = $this->show_header();
    $content = $this->show_folder($entries);
    $options = $this->show_options();

    if (isset($_GET['message'])) {
      $this->add_message($_GET['message']);
    }

    $folder = "{$this->url['full']}?path=" . $this->path_encode($this->path['relative']);
    $search = array("/%top_path%/", "/%columns%/", "/%cover_image%/", "/%error_msg%/", 
                    "/%stream_options%/", "/%content%/", "/%folder_path%/", "/%thumb_size%/",
                    "/%rss_url%/", "/%rss_title%/");
    $replace = array($topPath, $this->columns, $coverImage, $this->infoMessage, 
                     $options, $content, $folder, $this->thumbSize,
                     htmlentities("$folder&stream=rss"), "{$this->path['relative']} podcast");

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
        for ($row = 0; $row < $rows; $row++) {
          $entry .= "<tr>";
          for ($i = 0; $i < $this->columns; $i++) {
            $cell = $row + ($i * $rows);
            $item = @ $group[$cell];
            $urlPath = $this->path_encode("{$this->path['relative']}/$item");
            
            $entry .= '<td valign="top">';
            if (empty($item)) {
              $entry .= "&nbsp;";
            } elseif (is_dir("{$this->path['full']}/$item")) {
              # Folder link
              $entry .= "<a title=\"Play files in this folder\" "
                . "href=\"{$this->url['relative']}?path=$urlPath&amp;stream={$this->streamType}\"><img border=0 "
                . "alt=\"|&gt; \" src=\"play.gif\"></a>\n"
                . "<a href=\"{$this->url['relative']}?path=$urlPath\">$item/</a>\n";
            } else {
              # File link
              $entry .= "<a href=\"{$this->url['relative']}?path=$urlPath\">"
                . "<img src=\"download.gif\" border=0 title=\"Download this song\" alt=\"[Download]\"></a>\n"
                . "<a title=\"Play this song\" href=\"{$this->url['relative']}?path=$urlPath&amp;stream={$this->streamType}\">$item</a>\n";
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

  function show_options() {
    $select = array();
    foreach ($this->playlists as $list) {
      $select[$list] = "";
    }
    if (strlen($this->player) > 0 && $this->allowLocal) {
      $select['player'] = "";
    }
    if (strlen($this->slimserverUrl) > 0 && $this->allowLocal) {
      $select['slim'] = "";
    }
    if (array_key_exists($this->streamType, $select)) {
      $select[$this->streamType] = 'CHECKED';
    }
    $output = "";
    foreach ($select as $type => $checked) {
      switch ($type) {
        case "player":
          $display = "Play on server";
          break;
        case "slim":
          $display = "Play on slimserver";
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
   * @return array An associative array with 'numfiles' (number of files only) and 'items' (all allowed file and folder names)
   */
  function list_folder($path) {
    $folderHandle = dir($path);
    $entries = array();
    $numFiles = 0;
    while (false !== ($entry = $folderHandle->read())) {
      $fullPath = "$path/$entry";
      if (!($entry{0} == '.') && 
          (is_dir($fullPath) || (is_file($fullPath) && $this->valid_suffix($fullPath))) ) {
        $entries[] = $entry;
      }
    }
    $folderHandle->close();
    natcasesort($entries);
    return $entries;
  }

  /**
   * Fetches streamtype from $_POST or $_COOKIE.
   * @return string streamtype as 'pls' or 'm3u'
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
    
    switch ($streamType) {
      case 'rss':
        break;
      case 'player':
        if (strlen($this->player) == 0) $streamType = '';
        break;
      case 'slim':
        if (!$this->allowLocal) $streamType = '';
        break;
      case 'asx':
      case 'pls':
      case 'm3u':
        if (in_array($streamType, $this->playlists)) {
          if ($setcookie) setcookie('streamtype', $streamType);
        } else {
          $streamType = '';
        }
        break;
      default:
        $streamType = '';
    }
    if (empty($streamType) && count($this->playlists) > 0) {
      $streamType = $this->playlists[0];
    }
    return $streamType;
  }

  /**
   * @return string Formatted HTML with cover image (if any)
   */
  function show_cover() {
    $link = $this->cover_image();
    if (!empty($link)) {
      return "<a href=\"$link\"><img border=0 src=\"$link\" width={$this->thumbSize} "
                 . "height={$this->thumbSize} align=left></a>";
    }
    return "";
  }
  
  function cover_image() {
    $covers = array("cover.jpg", "Cover.jpg", "folder.jpg", "Folder.jpg", "cover.gif", "Cover.gif",
                  "folder.gif", "Folder.gif");
    foreach ($covers as $cover) {
      if (is_readable("{$this->path['full']}/$cover")) {
        return "{$this->url['relative']}?path=" . $this->path_encode("{$this->path['relative']}/$cover");
      }
    }
    return "";
  }

  /**
   * @return string Formatted HTML with bread crumbs for folder
   */
  function show_header() {
    $path = $this->path['relative'];
    $parts = explode("/", trim($path, "/ "));
    if ($parts[0] == '') {
      $parts = array();
    }
    if (count($parts) > 0) {
      $items = array("<b><a href=\"{$this->url['relative']}?path=\">{$this->homeName}</a></b>");
    } else {
      $items = array("<b>{$this->homeName}</b>");
    }
    $currentPath = $encodedPath = "";
    for ($i = 0; $i < count($parts); $i++) {
      $currentPath .= "/{$parts[$i]}";
      $encodedPath = $this->path_encode($currentPath);
      if ($i < count($parts) - 1) {
        $items[] = "<b><a href=\"{$this->url['relative']}?path=$encodedPath\">{$parts[$i]}</a></b>\n";
      } else {
        $items[] = "<b>{$parts[$i]}</b>";
      }
    }
    $output = implode(" &raquo; ", $items);

    # Output "play all"
    $output .= "&nbsp;&nbsp;<a href=\"{$this->url['relative']}?path=$encodedPath&amp;stream={$this->streamType}\"><img 
      src=\"play.gif\" border=0 title=\"Play all songs in this folder as {$this->streamType}\"
      alt=\"Play all songs in this folder as {$this->streamType}\"></a>";
    return $output;
  }

  /**
   * Checks if $entry has any of the $suffixes
   *
   * @return boolean True if valid.
   */
  function valid_suffix($entry) {
    foreach ($this->suffixes as $suffix) {
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
    $fullPath = "{$this->path['root']}/$folder";
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
      $this->play_slimserver($this->path['relative']);
      return;
    }
    
    $fullPath = $this->path['full'];
    $name = pathinfo($fullPath, PATHINFO_BASENAME);
    $items = array();
    
    if (is_dir($fullPath)) {
      $items = $this->folder_items($this->path['relative'], $items);
      natcasesort($items);
    } else {
      $items[] = $this->path['relative'];
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
        $url = "{$this->url['full']}?path=" . $this->path_encode($this->path['relative']) . "&stream=rss";
        $image = $this->url['root'] . "/" . $this->cover_image();
        $this->streamLib->playlist_rss($entries, $name, $url, $image);
        break;
      case "m3u":
        $this->streamLib->playlist_m3u($entries, $name);
        break;
      case "pls":
        $this->streamLib->playlist_pls($entries, $name);
        break;
      case "asx":
        $this->streamLib->playlist_asx($entries, $name);
        break;
      case "player":
        if ($this->allowLocal) {
          $this->play_files($items);
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
    $name = preg_replace($search, $replace, $item);
    $fullUrl = $this->path_encode($item);
    $entry = array('title' => $name, 'url' => "{$this->url['full']}?path=$fullUrl");
    if ($withTimestamp) {
      $entry['timestamp'] = filectime("{$this->path['root']}/$item");
    }
    return $entry;
  }

  /**
   * play_files uses system() and might be VERY UNSAFE!
   */
  function play_files($items) {
    $args = "";
    foreach ($items as $item) {
      $args .= "\"{$this->path['root']}/$item\" ";
    }
    system("{$this->player} $args >/dev/null 2>/dev/null &", $ret);
    if ($ret === 0) {
      $this->add_message("Playing requested file(s) on server");
    } else {
      $this->add_message("Error playing file(s) on server.  Check the error log.");
    }
    $this->reload_page();
  }

  function play_slimserver($item) {
     $url = $this->slimserverUrl . urlencode("/$item") . $this->slimserverUrlSuffix; 
     $data = $this->http_get($url);
     if (strlen($data) == 0) {
       $this->add_message("Error reaching slimserver");
     } else {
       $this->add_message("Playing requested file(s) on slimserver");
     }
     $this->reload_page();
  }
  
  /**
   * Redirect to current folder page.
   */
  function reload_page() {
    $path = $this->path['relative'];
    if (is_file($this->path['full'])) {
      $path = preg_replace("|/[^/]+$|", "", $path);
    }
    $url = "{$this->url['full']}?path=" . $this->path_encode($path);
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
   * Try to resolve safe path.
   */
  function resolve_path($rootPath) {
    if (!empty($rootPath) && !is_dir($rootPath)) {
      $this->fatal_error("The \$config['path'] \"$rootPath\" isn't readable");
    }
    if (empty($rootPath)) {
       $rootPath = getcwd();
    }
    
    $relPath = "";
    if (isset($_GET['path'])) {
      # Most iso-8859-1 letters, minus " and \
      $getPath = preg_replace("/[^\x20\x21\x23-\x7e\xa0-\xff]/", "", $_GET['path']);
      $getPath = stripslashes($getPath);
      
      if (is_readable("$rootPath/$getPath")) {
        $relPath = $getPath;
      } else {
        $this->add_message("The path <i>$getPath</i> is not readable.");
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
    return array('full' => $fullPath, 'relative' => $relPath, 'root' => $rootPath);
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
    $relative = pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_BASENAME);
    return array('full' => "$root/$relative", 'relative' => $relative, 'root' => $root);
  }

  /**
   * Encode a fairly readable path for the URL.
   */
  function path_encode($path) {
     $search = array("|^%2F|", "|%20|", "|%2F|");
     $replace = array("", "+", "/");
     return preg_replace($search, $replace, rawurlencode($path)); 
  }
}


class StreamLib {

  /**
   * @param array $entries Array of arrays with keys moreinfo, url, starttime, duration, title, author & copyright
   * @param string $name Stream name
   */
  function playlist_asx($entries, $name = "playlist") {

     $output = "<asx version=\"3.0\">\n";
     foreach ($entries as $entry) {
        $output .= "  <entry>\n";
        $output .= "    <ref href=\"{$entry['url']}\" />\n";
        if (isset($entry['moreinfo']))  $output .= "    <moreinfo href=\"{$entry['moreinfo']}\" />\n";
        if (isset($entry['starttime'])) $output .= "    <starttime value=\"{$entry['starttime']}\" />\n";
        if (isset($entry['duration']))  $output .= "    <duration value=\"{$entry['duration']}\" />\n";
        if (isset($entry['title']))     $output .= "    <title>{$entry['title']}</title>\n";
        if (isset($entry['author']))    $output .= "    <author>{$entry['author']}</author>\n";
        if (isset($entry['copyright'])) $output .= "    <copyright>{$entry['copyright']}</copyright>\n";
        $output .= "  </entry>\n";
     }
     $output .= "</asx>\n";
     
     $this->stream_content($output, "$name.asx", "audio/x-ms-asf");
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
  function playlist_rss($entries, $name = "playlist", $link, $image = "") {
    $link = htmlentities($link);
    $name = htmlentities($name);
    $output = "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n"
            . "<rss xmlns:itunes=\"http://www.itunes.com/DTDs/Podcast-1.0.dtd\" version=\"2.0\">\n"
            . "  <channel><title>$name</title><link>$link</link>\n";
    if (!empty($image)) {
      $output .= "  <image><url>$image</url></image>\n";
    }
    foreach ($entries as $entry) {
      $date = date('r', $entry['timestamp']);
      $url = htmlentities($entry['url']);
      $title = htmlentities($entry['title']);
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
     $filename = pathinfo($file, PATHINFO_BASENAME);
     
     header("Content-Type: $mimetype");
     header("Content-Length: " . filesize($file));
     if ($isAttachment) header("Content-Disposition: attachment; filename=\"$filename\"", true);

     $this->readfile_chunked($file);
     exit(0);
  }

  /**
   *
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
      ob_flush();
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