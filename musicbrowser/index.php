<?php
$config = array(
  # Where your music is available on the file system (e.g. /mnt/my_music/mp3")
  # Leave empty to use the current directory.  Using the current directory will 
  # also enable fwd/rwd in players like Winamp.
  'path' => "",

  # Public URL for this script. This URL will be used in the playlist files (.m3u/.pls).
  # Leave empty to auto detect.
  'url' => "",
  
  # The template file
  'template' => "template.inc",
  
  # array of allowed music file extensions
  'fileTypes' =>  array("mp3", "ogg"),
  
  # Name of root entry in header
  'homeName' => "Music Browser",

  # Maximum number of entries in a playlist
  'maxPlaylistSize' => 100,
  
  # Whether to enable pls/m3u/asx playlists or not
  'enablePls' => true,
  'enableM3u' => true,
  'enableAsx' => false,
  
  # Enable the embedded flash player?
  'enableFlash' => true,


  # Slimserver URL.  The root URL for slimserver "Music folder" streaming, e.g.
  #  'slimserverUrl' => "http://myserver:9000".  Leave blank to disable.
  'slimserverUrl' => "",
  
  # Slimserver player ID. Either a MAC address or IP number.  See Player Settings - Player Information 
  # in the slimserver web interface, e.g. "12:34:8A:34:DE:11";
  'slimserverPlayer' => "",

  # Array of regular expression (regexp) matches for hosts that are allowed to use server 
  # playback and/or slimserver playback, e.g.
  #  'allowLocal' => array("/^10\.0\.0\./")
  # Leave as array() to disable.
  'allowLocal' => array(),
   
  # Array of regular expression (regexp) matches for files/directories to hide
  'hideItems' => array("/^lost\+found/", "/^\./"),
  
  # Play music on the server.  Full path to player with options, e.g.
  #  'player' => "killall madplay; /usr/bin/madplay",
  # Leave blank to disable.
  'player' => "",

  # Set to true to display PHP errors and notices.  Should be set to false.
  'debug' => false,
);

require('streamlib.php');
$musicBrowser = new MusicBrowser($config);
$musicBrowser->show_page();
?>