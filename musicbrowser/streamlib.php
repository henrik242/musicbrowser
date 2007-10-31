<?php

/**
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
  
  var $columns = 5;
  var $errorMsg = "";
  var $headingThreshold = 15;
  var $homeName, $path, $url, $streamLib;
  var $suffixes, $streamType, $template;
  var $player;
  
  /**
   * @param array $config Assosciative array with configuration
   *                      Keys: url, path, fileTypes, template, homeName
   */  
  function MusicBrowser($config) {
    #ini_set("error_reporting", E_ALL);
    ini_set("display_errors", 0);

    if (!is_dir($config['path'])) {
      $this->fatal_error("The \$config['path'] \"{$config['path']}\" isn't readable");
    }
    if (!is_readable($config['template'])) {
      $this->fatal_error("The \$config['template'] \"{$config['template']}\" isn't readable");
    }
    
    $this->suffixes = $config['fileTypes'];
    $this->homeName = $config['homeName'];
    $this->template = $config['template'];
    $this->player = $config['player'];
    $this->url = $this->resolve_url($config['url']);
    $this->path = $this->resolve_path($config['path']);
    
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
    
    # If streaming is requested, do it
    if ((is_dir($fullPath) || is_file($fullPath)) && isset($_GET['stream'])) {
      $this->stream_all($_GET['type']);
      exit(0);
    }

    # If the path is a file, download it
    if (is_file($fullPath)) {
      $this->streamLib->stream_file_auto($fullPath);
      exit(0);
    } 

    # List of files and folders
    $folder = $this->list_folder($fullPath);

    # Set stream type as pls or m3u from $_POST or $_COOKIE
    $this->streamType = $this->set_stream_type();

    # get all content for template
    $coverImage = $this->show_cover();
    $topPath = $this->show_header($folder['numfiles']);
    $content = $this->show_folder($folder['items']);
    $options = $this->show_options();

    if (isset($_GET['message'])) {
      $this->add_error($_GET['message']);
    }
    $folder = "{$this->url['full']}?path=" . $this->path_encode($this->path['relative']);
    $search = array("/%top_path%/", "/%columns%/", "/%cover_image%/", "/%error_msg%/", 
                    "/%stream_options%/", "/%content%/", "/%folder_path%/");
    $replace = array($topPath, $this->columns, $coverImage, $this->errorMsg, 
                     $options, $content, $folder);

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
              $item = htmlentities($item);
              $entry .= "<a href=\"{$this->url['relative']}?path=$urlPath\">$item/</a>\n";
            } else {
              # File link
              $item = htmlentities($item);
              $entry .= "<a href=\"{$this->url['relative']}?path=$urlPath\"><img 
                src=\"download.gif\" border=0 title=\"Download this song\" alt=\"[Download]\"></a>\n";
              $entry .= "<a title=\"Play this song\" "
                       ."href=\"{$this->url['relative']}?path=$urlPath&amp;stream&amp;type={$this->streamType}\">$item</a>";
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
    $select = array('pls' => "", 'm3u' => "");
    if (strlen($this->player) > 0) {
      $select['player'] = "";
    }
    $select[$this->streamType] = 'CHECKED';
    $output = ""; 
    foreach ($select as $type => $checked) {
      if ($type == "player") {
        $display = "Play on server";
      } else {
        $display = $type;
      }
      $output .= "<input $checked type=\"radio\" name=\"streamtype\" value=\"$type\" "
               . " onClick=\"document.streamtype.submit()\">$display\n";
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
   * @return array Associative array with 'm3u' and 'pls', where one of the values are 'CHECKED'
   */
  function stream_selector() {
    $select = array('pls' => '', 'm3u' => '', 'player' => '');
    $select[$this->streamType] = 'CHECKED';
    return $select;
  }

  /**
   * List folder content.
   * @return array An associative array with 'numfiles' (number of files only) and 'items' (all allowed file and folder names)
   */
  function list_folder($fullPath) {
    $folderHandle = dir($fullPath);
    $items = array();
    $numFiles = 0;
    while (false !== ($entry = $folderHandle->read())) {
      $fullEntry = "$fullPath/$entry";
      if (!($entry{0} == ".") && (is_dir($fullEntry) || $this->valid_suffix($entry))) {
        $items[] = $entry;
        if (is_file($fullEntry)) {
          $numFiles++;
        }
      }
    }
    $folderHandle->close();
    return array('numfiles' => $numFiles, 'items' => $items);
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
      if (strlen($this->player) == 0) {
        $streamType = "";
      }
    }
    switch ($streamType) {
      case 'pls':
      case 'm3u':
      case 'player':
        if ($setcookie) setcookie('streamtype', $streamType);
        return $streamType;
      default:
        return 'm3u';
    }
  }

  /**
   * @return string Formatted HTML with cover image (if any)
   */
  function show_cover() {
    
    $covers = array("cover.jpg", "Cover.jpg", "folder.jpg", "Folder.jpg", "cover.gif", "Cover.gif",
                  "folder.gif", "Folder.gif");
    $output = "";              
    foreach ($covers as $cover) {
      if (is_readable("{$this->path['full']}/$cover")) {
        $link = "{$this->url['relative']}?path=" . $this->path_encode("{$this->path['relative']}/$cover");
        $output .= "<a href=\"$link\"><img border=0 src=\"$link\" width=150 height=150 align=left></a>";
        break;
      }
    }
    return $output;
  }

  /**
   * @return string Formatted HTML with bread crumbs for folder
   */
  function show_header($numfiles) {
    $path = $this->path['relative'];
    $parts = $this->explode_modified($path);
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

    # Output "play all" if there are files in this folder
    if ($numfiles > 0) {
      $output .= "&nbsp;&nbsp;<a href=\"{$this->url['relative']}?path=$encodedPath&amp;stream&amp;type={$this->streamType}\"><img 
        src=\"play{$this->streamType}.gif\" border=0 title=\"Play all songs in this folder as {$this->streamType}\"
        alt=\"Play all songs in this folder as {$this->streamType}\"></a>";
    }
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
   * Stream folder or file.
   */
  function stream_all($type) {
    $fullPath = $this->path['full'];
    $name = pathinfo($fullPath, PATHINFO_BASENAME);
    $items = array();
    
    if (is_dir($fullPath)) {
      # $fullPath is a folder with mp3's
      $folderHandle = dir($fullPath);
      while (false !== ($entry = $folderHandle->read())) {
        if (!($entry{0} == '.') && $this->valid_suffix($entry)) {
          $items[] = "{$this->path['relative']}/$entry";
          continue;
        }
      }
      natcasesort($items);
      $folderHandle->close();
    } else {
      # $fullPath is an mp3  
      $items[] = $this->path['relative'];
    }

    $entries = array();
    foreach ($items as $item) {
      $entries[] = $this->entry_info($item);
    }

    switch ($type) {
      case "m3u":
        $this->streamLib->stream_m3u($entries, $name);
        break;
      case "pls":
        $this->streamLib->stream_pls($entries, $name);
        break;
      case "player":
        $this->play_files($items);
        break;
    }
  }

  /**
   * Info for entry in playlist.
   */
  function entry_info($item) {
    $search = array("|\.[a-z0-9]{1,4}$|i", "|/|");
    $replace = array("", " - ");
    $name = preg_replace($search, $replace, $item);
    $fullUrl = $this->path_encode($item);
    return array('title' => $name, 'url' => "{$this->url['full']}?path=$fullUrl");
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
      $message = "Playing requested file(s) on server";
    } else {
      $message = "Error playing file(s) on server.  Check the error log.";
    }
    $folder = preg_replace("|/?[^/]*$|", "", $this->path['relative']);
    $encodedPath = $this->path_encode($folder);
    $message = $this->path_encode($message);
    header("Location: {$this->url['full']}?path=$encodedPath&message=$message");
    exit(0);
  }

  /**
   * As explode with / as delimiter, but trims slashes and returns array() instead array with empty element.
   */
  function explode_modified($thePath) {
    $parts = explode("/", trim($thePath, "/"));
    if (count($parts) == 1 && strlen($parts[0]) == 0) {
      return array();
    } else {
      return $parts;
    }
  }

  /**
   * Add message to be displayed as error.
   */
  function add_error($msg) {
    $this->errorMsg .= "$msg<br>\n";
  }

  /**
   * Try to resolve safe path.
   */
  function resolve_path($rootPath) {
    $relPath = "";
    if (isset($_GET['path'])) {
      # Most iso-8859-1 letters, minus " and \
      $getPath = preg_replace("/[^\x20\x21\x23-\x7e\xa0-\xff]/", "", $_GET['path']);
      $getPath = preg_replace('/\\\/', "", $getPath);
      
      if (is_readable("$rootPath/$getPath")) {
        $relPath = $getPath;
      } else {
        $this->add_error("The path <i>$getPath</i> is not readable.");
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
  function stream_asx($entries, $name = "playlist") {

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
  function stream_pls($entries, $name = "playlist") {
     $output = "[playlist]\n";
     $output .= "X-Gnome-Title=$name\n";
     $output .= "NumberOfEntries=" . count($entries) . "\n";
     $counter = 1;
     foreach ($entries as $entry) {
        $output .= "File$counter={$entry['url']}\n";
        $output .= "Title$counter={$entry['title']}\n";
        $output .= "Length$counter=-1\n";
        $counter++;
     }
     
     $output .= "Version=2\n";
     
     $this->stream_content($output, "$name.pls", "audio/x-scpls");
  }

  /**
   * @param array $entries Array of arrays with keys url, title
   * @param string $name Stream name
   */
  function stream_m3u($entries, $name = "playlist") {
     $output = "#EXTM3U\n";
     foreach ($entries as $entry) {
        $output .= "#EXTINF:0, {$entry['title']}\n";
        $output .= "{$entry['url']}\n";
     }
     
     $this->stream_content($output, "$name.m3u", "audio/x-mpegurl");
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
  function stream_content($content, $name, $mimetype) {
     header("Content-Disposition: attachment; filename=\"$name\"", true);
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