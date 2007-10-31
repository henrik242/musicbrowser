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

Changelog
---------
0.3 - Lower PHP requirement to 4.2
    - Basic support for server side playback
    - Prettier URLs
    - Move MusicBrowser class from index.php to streamlib.php

0.2 - Lower PHP requirement to 4.3

0.1 - Initial release
    - Requires PHP 5.1

Contact
-------
Contact me at musicbrowser (at) henrik (dot) synth (dot) no
