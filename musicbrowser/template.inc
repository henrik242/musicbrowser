<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<!--
   This file is part of Music Browser.

   Music Browser is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   any later version.

   Music Browser is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with Music Browser.  If not, see <http://www.gnu.org/licenses/>.

   Copyright 2006-2008 Henrik Brautaset Aronsen
-->
<html>
<head>
  <title></title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <style type="text/css">
<!-- 
.header { background-color: #91AA9D; }
.footer { background-color: #91AA9D; font-size: 0.8em; }
th { background-color: #3E606F; color: #FCFFF5; }
a { color: #2222AA; }
.error { color: red; }
.footerlink { text-decoration: none; }
a.footerlink:hover { text-decoration: underline; }
.folder { font-size: 0.8em; }
.file { font-size: 0.8em; }
.cell { vertical-align: bottom; text-align: left; }
.odd { background-color: #FCFFF5; }
.even { background-color: #D1DBBD; }
.title { font-weight: bold; }
.path { font-weight: bold; }
-->
  </style>
  <script type="text/javascript" src="swfobject.js"></script>
</head>
<body onLoad="changeDir('%folder%')">

  <p><div id=error class=error></div>
  <table align=center width="95%"><tr><td colspan=5>
    <!-- header -->
    <table class=header width="100%"><tr><td width=400>
      <div id=cover></div>
      </td><td align=center>
      	<div id="breadcrumb"></div>
    </td><td align=right width=400>
      %flash_player%
    </td></tr></table>
    <!-- end header -->
  </td></tr><tr><td colspan=5>
    <div id=content align=center><i>initializing...</i></div>
  </td></tr>
  <tr><td></td></tr><tr><td colspan=5>
    <!-- footer -->
    <table class=footer width="100%"><tr><td width=150>
        <a class=footerlink href="" id=podcast>podcast</a>
      </td><td align=center>
      <form method=post action="" name=streamtype>
        <div id=options></div>
      </form>
    </td><td width=150 align=right>
      <a class=footerlink href="http://musicbrowser.sf.net/">musicbrowser.sf.net</a>
    </td></tr></table>
    <!-- end footer -->
  </td></tr></table>
</body></html>