<?php

class StreamLib {

  /**
   * @param array $entries Array of arrays with keys moreinfo, url, starttime, duration, title, author & copyright
   * @param string $name Stream name
   */
  function stream_asx(array $entries, $name = "playlist") {

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
  function stream_pls(array $entries, $name = "playlist") {
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
  function stream_m3u(array $entries, $name = "playlist") {
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
  function stream_show_entries(array $entries, $name = "playlist") {
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