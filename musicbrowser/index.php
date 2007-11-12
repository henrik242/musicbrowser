<?php
$config = array(
  # Where your music is available on the file system
  # Leave empty to use the current directory.
  'path' => "/mnt/my_music/",

  # Public URL for this script. This URL will be used in the playlist files (.m3u/.pls).
  # Leave blank to auto detect.
  'url' => "",
  
  # The template file
  'template' => "template.inc",
  
  # array of allowed music file extensions
  'fileTypes' =>  array("mp3", "ogg", "mp4", "m4a"),
  
  # Name of root entry in header
  'homeName' => "Home",

  # Maximum number of entries in a playlist
  'maxPlaylistSize' => 100,

  # Whether to enable pls/m3u/asx playlists or not
  'enablePls' => true,
  'enableM3u' => true,
  'enableAsx' => false,

  # Slimserver URL.  The root URL for slimserver "Music folder" streaming, e.g.
  #  'slimserverUrl' => 'http://myserver:9000/status_header.html?p0=playlist&p1=play&p2=file%3A%2F%2F%2Fmnt%2Fmy_music'
  #  'slimserverUrlSuffix' => '&player=00%3A05%3A21%3A07%3A61%3A43'
  # Leave blank to disable.
  'slimserverUrl' => "",
  'slimserverUrlSuffix' => "",

  # Array of matches for hosts that are allowed to use server playback and/or slimserver playback, e.g.
  #  'allowLocal' => array("/^10\.0\.0\./")
  # Leave as array() to disable.
  'allowLocal' => array(),
  
  # Play music on the server.  Full path to player with options, e.g.
  #  'player' => "killall madplay; /usr/bin/madplay",
  # Leave blank to disable.
  'player' => "",
);

require('streamlib.php');
$musicBrowser = new MusicBrowser($config);
$musicBrowser->show_page();
?>