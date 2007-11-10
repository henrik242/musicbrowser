Installation
------------
1) Make sure your web server supports at least PHP 4.2 (PHP 5 works as well)
2) Edit the top of index.php to match your site
3) Copy all the files in the distribution to a web acessible path 

Installation example (Debian)
-----------------------------
1) $ sudo apt-get install lighttpd php5-cgi
   $ cd /etc/lighttpd/conf-enabled
   $ sudo ln -s ../conf-available/10-fastcgi.conf .
   $ sudo /etc/init.d/lighttpd restart
2) $ unzip musicbrowser.zip
   $ vim musicbrowser/index.php (i to edit, esc-:wq-return to exit)
3) $ cp -R musicbrowser /var/www/
   Music Browser is now available at http://yourhost/musicbrowser/

Local playback on the webserver
-------------------------------
- If your webserver has a soundcard with loudspeakers connected, you should
  be able to use a local player.
- Crude example: set "player" => "killall madplay; /usr/bin/madplay" in
  index.php
- Make sure the web server user (e.g. www-data) has write permission to the
  sound device, e.g. by "sudo chmod a+w /dev/dsp*"

Playback via Slimserver
-----------------------
- Make sure your Slimserver is running
- Navigate to Slimserver -> Browse -> Music Folder in a web browser
- Right-click on the play symbol on a folder and select "copy link location"
- Paste the link into a text editor.  It should look something like this:
  "http://myserver:9000/status_header.html?p0=playlist&p1=play&p2=file%3A%2F%2F%2Fmnt%2Fmy_music%2FAbsurd%2520Minds&player=00%3A04%3A20%3A07%3A62%3A46"
- Copy the part before the folder name (here: "Absurd%2520Minds") into the config 'slimserverUrl':
  "http://myserver:9000/status_header.html?p0=playlist&p1=play&p2=file%3A%2F%2F%2Fmnt%2Fmy_music"
- Copy the part after the folder name into the config 'slimserverUrlSuffix':
  "&player=00%3A04%3A20%3A07%3A62%3A46"
- Music Browser should support Slimserver playback now.

Changelog
---------
0.5-CVS
- All folders are available as podcasts (rss feeds)
- Bugfix: slimserver checkbox didn't work when server playback was disabled

0.4
- Rudimentary Slimserver support
- Fix quoting bug when magic_quotes_sybase was enabled
- Fix display error when file names are utf-8 encoded
- Possibility to limit slimserver and server side playback to allowed hosts

0.3
- lower PHP requirement to 4.2
- very basic support for server side playback
- prettier URLs
- move MusicBrowser class from index.php to streamlib.php
- play icon for all folders
- play folders recursively

0.2
- lower PHP requirement to 4.3

0.1
- initial release
- requires PHP 5.1

Contact
-------
Contact me at musicbrowser (at) henrik (dot) synth (dot) no
