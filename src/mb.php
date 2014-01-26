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
 *   Copyright 2006-2013 Henrik Brautaset Aronsen
 */

init();

function init() {
  global $mb;

  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  date_default_timezone_set('UTC');

  $mb = array();
  $mb['allowed_filetypes'] = array("mp3", "ogg");
  $mb['base_path'] = getcwd();

  $server_url = isset($_SERVER["HTTPS"]) ? "https" : "http" . "://" . $_SERVER["HTTP_HOST"];
  $mb['script_url'] = $server_url . $_SERVER["SCRIPT_NAME"]; 
  $mb['query_url'] =  $server_url . $_SERVER["REQUEST_URI"];
  $mb['folder_url'] = preg_replace("#/[^/]+$#", "", $mb['script_url']); 
  $mb['stream_through_php'] = FALSE;

  $mb['template'] = "mb.template";

  $path = resolve_path();

  if ($path['path'] === NULL) {
    four_oh_four();
  }

  run_action($path, isset($_GET['recursive']));
}

function four_oh_four() {
  header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found"); 
  echo("404 File Not Found");
  exit();
}

function run_action($path, $recursive) {

  switch ($path['suffix']) {
    case NULL:
      if (!$path['isdir']) {
        $entries = read_dir($path['path'], $recursive, FALSE);
        if (count($entries) > 0) {
          stream_file(array_pop($entries));
        } else {
          four_oh_four();
        }
      } else {
        show_page();
      }
      break;
    case "json":
      $entries = read_dir($path['path'], $recursive, TRUE, TRUE);
      print(json_format($entries));
      break;
    case "m3u":
      $entries = read_dir($path['path'], $recursive);
      print(playlist_m3u($entries));
      break;
    case "pls":
      $entries = read_dir($path['path'], $recursive);
      print(playlist_pls($entries));
      break;
    case "asx":
      $entries = read_dir($path['path'], $recursive);
      print(playlist_asx($entries));
      break;
    case "rss":
      $entries = read_dir($path['path'], $recursive);
      print(playlist_rss($entries, "playlist",  $query_url));
      break;
    case "xspf":
      $entries = read_dir($path['path'], $recursive);
      print(playlist_xspf($entries, "playlist",  $query_url));
      break;
    default:
      four_oh_four();
      break;
  }
}

function show_page() {
  global $mb;

  $template = file_get_contents($mb['template']);
  print($template);
}

function resolve_path() {
  global $mb;

  $path = $mb['base_path'] . @$_SERVER['PATH_INFO'];

  if (!file_exists($path)) {
    preg_match("/^(.+)\.([a-zA-Z0-9]+)$/", $path, $matches);

    $prefix = @$matches[1];
    $suffix = @$matches[2];
    if ($prefix !== NULL && file_exists($prefix)) {
      $path = $prefix;  
    } else {
      $path = NULL;
    }
  }
  if (!preg_match("#^" . getcwd() . "#", realpath($path))) {
    $path = NULL;
  }

  return array('path' => $path, 'suffix' => @$suffix, 'isdir' => is_dir($path));
}

function read_dir($path, $recursive = FALSE, $empty_dirs = FALSE, $add_coverimage = FALSE) {
  if (is_file($path)) {
    if (allowed_filetype($path)) {
      return array(strip_base_path($path));
    } else {
      return array();
    }
  }

  if (substr($path, -1) !== "/") {
    $path .= "/";
  } 

  $coverimage = NULL;
  $entries = array();
  if ($handle = opendir($path)) {
    while (FALSE !== ($entry = readdir($handle))) {
      if ($entry === "." || $entry === "..") {
        continue;
      }

      $entry = $path . $entry;

      if (is_dir($entry)) {
        if ($empty_dirs) {
          $entries[] = strip_base_path($entry) . "/";
        }
        if ($recursive) {
          $entries = array_merge($entries, read_dir($entry, $recursive, $empty_dirs));
        }
      } elseif ($add_coverimage && $coverimage === NULL && is_image($entry)) {
        $coverimage = strip_base_path($entry);
      } elseif (allowed_filetype($entry)) {
        $entries[] = strip_base_path($entry);
      }
    }
    closedir($handle);
  }
  if ($add_coverimage) {
    return array_merge(array($coverimage), $entries);
  }
  return $entries;
}


function is_image($entry) {
  if (preg_match("/play.gif$/", $entry) || preg_match("/download.gif$/", $entry)) {
    return FALSE;
  }
  $filetypes = array('png', 'jpg', 'jpeg', 'gif');
  foreach ($filetypes as $type) {
    if (preg_match("/\." . $type . "$/", $entry)) {
      return TRUE;
    }
  }
  return FALSE;
}


function strip_base_path($entry) {
  global $mb;
  return preg_replace("#" . $mb['base_path'] . "/#", "", $entry);
}


function allowed_filetype($entry) {
  global $mb;

  foreach ($mb['allowed_filetypes'] as $filetype) {
    if (preg_match("/\." . $filetype . "$/i", $entry)) {
      return TRUE;
    }
  }
  return FALSE;
}


function json_format($array) {
  $json = array();
  $search = array('|"|', "/[\n\r]/");
  $replace = array('\\"', '');

  foreach ($array as $value) {
    $json[] = ' "' . preg_replace($search, $replace, $value) . '"';
  }
  return "[\n" . implode($json, ",\n") . "\n]";
}


function metadata($entry) {
  global $mb;

  if ($mb['stream_through_php']) {
    $url = $mb['script_url'] . "/$entry";
  } else {
    $url = $mb['folder_url'] . "/$entry";
  }
  $title = array_pop(preg_split("#/#", $entry));
  $title = preg_replace("/\.[a-zA-Z0-9]+$/", "", $title);
  $title = preg_replace("/[_\.]/", " ", $title);

  return array('title' => $title, 'url' => $url, 'length' => -1, 'timestamp' => NULL, 'mediatype' => "audio/mpeg");
}


function convert_to_utf8($entry, $from_charset) {
  if ($from_charset != "utf-8") {
    $entry = mb_convert_encoding($entry , "utf-8", $from_charset);
  }
  return $entry;
}


function playlist_asx($entries, $name = "playlist", $from_charset = "utf-8") {
  $output = "<asx version=\"3.0\">\r\n";
  $output .= "<param name=\"encoding\" value=\"utf-8\" />\r\n";
  foreach ($entries as $entry) {
    $meta = metadata($entry);
    $title = convert_to_utf8($meta['title'], $from_charset);
    $output .= "  <entry>\r\n";
    $output .= "    <ref href=\"{$meta['url']}\" />\r\n";
    if (isset($meta['moreinfo']))  $output .= "    <moreinfo href=\"{$meta['moreinfo']}\" />\r\n";
    if (isset($meta['starttime'])) $output .= "    <starttime value=\"{$meta['starttime']}\" />\r\n";
    if (isset($meta['duration']))  $output .= "    <duration value=\"{$meta['duration']}\" />\r\n";
    if (isset($meta['title']))     $output .= "    <title>$title</title>\r\n";
    if (isset($meta['author']))    $output .= "    <author>{$meta['author']}</author>\r\n";
    if (isset($meta['copyright'])) $output .= "    <copyright>{$meta['copyright']}</copyright>\r\n";
    $output .= "  </entry>\r\n";
  }
  $output .= "</asx>\r\n";

  return stream_content($output, "$name.asx", "audio/x-ms-asf");
}


function playlist_pls($entries, $name = "playlist") {
  $output = "[playlist]\r\n";
  $output .= "X-Gnome-Title=$name\r\n";
  $counter = 0;
  foreach ($entries as $entry) {
    $counter++;
    $meta = metadata($entry);
    $output .= "File$counter={$meta['url']}\r\n"
            . "Title$counter={$meta['title']}\r\n"
            . "Length$counter={$meta['length']}\r\n";
  }
  $output .= "NumberOfEntries=$counter\r\n";
  $output .= "Version=2\r\n";
 
  return stream_content($output, "$name.pls", "audio/x-scpls");
}


function playlist_m3u($entries, $name = "playlist") {
  $output = "#EXTM3U\r\n";
  foreach ($entries as $entry) {
    $meta = metadata($entry);
    $output .= "#EXTINF:0, {$meta['title']}\r\n"
             . "{$meta['url']}\r\n";
  }
   
  return stream_content($output, "$name.m3u", "audio/x-mpegurl");
}


function playlist_rss($entries, $name = "playlist", $link, $image = "", $charset = "iso-8859-1") {
  $link = htmlspecialchars($link);
  $name = convert_to_utf8(htmlspecialchars($name), $charset);
  $output = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n"
	  . "<rss xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\" xmlns:atom=\"http://www.w3.org/2005/Atom\" version=\"2.0\">\r\n"
	  . "<channel><title>$name</title><link>$link</link>\r\n"
	  . "  <description>$name</description>\r\n"
	  . "  <atom:link href=\"$link&amp;stream=rss\" rel=\"self\" type=\"application/rss+xml\" />\r\n";
  if (!empty($image)) {
    $output .= "  <image><url>$image</url><title>$name</title><link>$link</link></image>\r\n";
  }
  foreach ($entries as $entry) {
    $meta = metadata($entry);
    $date = date('r', $meta['timestamp']);
    $url = htmlspecialchars($meta['url']);
    $title = convert_to_utf8(htmlspecialchars($meta['title']), $charset);
    $output .= "  <item><title>$title</title>\r\n"
	     . "    <enclosure url=\"$url\" length=\"{$meta['length']}\" type=\"{$meta['mediatype']}\" />\r\n"
	     . "    <guid>$url</guid>\r\n"
	     . "    <pubDate>$date</pubDate>\r\n"
	     . "  </item>\r\n";
  }
  $output .= "</channel></rss>\r\n";
  return stream_content($output, "$name.rss", "application/xml", "inline");
}


function playlist_xspf($entries, $name = "playlist", $link, $image = "", $charset = "utf-8") {
  $link = htmlspecialchars($link);
  $name = convert_to_utf8(htmlspecialchars($name), $charset);
  $output = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n"
	  . "<playlist version=\"1\" xmlns=\"http://xspf.org/ns/0/\">\r\n"
	  . "  <title>$name</title>\r\n"
	  . "  <trackList>\r\n";

  foreach ($entries as $entry) {
    $meta = metadata($entry);
    $url = htmlspecialchars($meta['url']);
    $title = convert_to_utf8(htmlspecialchars($meta['title']), $charset);
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
  return stream_content($output, "$name.xspf", "application/xspf+xml", "inline");
}


function stream_content($content, $name, $mimetype, $disposition = "attachment") {
  header("Content-Disposition: $disposition; filename=\"$name\"", TRUE);
  header("Content-Type: $mimetype", TRUE);
  header("Content-Length: " . strlen($content));
  return $content;
}


function stream_file($file) {
  global $mb;

  $suffix = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $isAttachment = TRUE;

  switch ($suffix) {
    case "mp3":
      $mimetype = "audio/mpeg";
      break;
    case "gif":
      $mimetype = "image/gif";
      $isAttachment = FALSE;
      break;
    case "png";
      $mimetype = "image/png";
      $isAttachment = FALSE;
      break;
    case "jpg":
    case "jpeg":
      $mimetype = "image/jpeg";
      $isAttachment = FALSE;
      break;
    default:
      $mimetype = "application/octet-stream";
      break; 
  }

  $filename = array_pop(explode("/", $file));
  header("Content-Type: $mimetype");
  header("Content-Length: " . filesize($file));
  if ($isAttachment) { 
    header("Content-Disposition: attachment; filename=\"$filename\"", TRUE);
  }
  return readfile_chunked($file);
}


function readfile_chunked($filename, $retbytes = TRUE) {
  $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
  $buffer = "";
  $cnt = 0;
  
  $handle = fopen($filename, "rb");
  if ($handle === FALSE) {
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




