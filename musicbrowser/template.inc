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
th { background-color: #3E606F; color: #FCFFF5; }
a { color: #2222AA; text-decoration: none; }
a:hover { text-decoration: underline; }
form { display: inline; }
.header { background-color: #91AA9D; }
.footer { background-color: #91AA9D; font-size: 0.8em; }
.folder { font-size: 0.8em; }
.file { font-size: 0.8em; }
.cell { vertical-align: bottom; text-align: left; }
.odd { background-color: #FCFFF5; }
.even { background-color: #D1DBBD; }
.title { font-weight: bold; }
.path { font-weight: bold; }
.loading { text-decoration: blink; text-align: center; font-style: italic; }
#box { display: block; color: black; position:fixed; padding: 1em; top: 30px; left: 70px; }
.box { padding: 10px; background-color: #CCC; }
.boxbutton { padding: 1px 5px; vertical-align: top; background-color: gray; color: white;  }
.boxbutton:link { text-decoration: none;  }
.error { color: red; }
.feet { text-decoration: none; padding-right: 10px; padding-left: 10px; }
.leftheader { text-align: left; vertical-align: top; }
.rightheader { text-align: right; vertical-align: top;}
#content { text-align: left; }
#breadcrumb { text-align: left; padding-bottom: 10px; }
.searchresult {list-style-type: none}
-->
  </style>
  <script type="text/javascript" src="musicbrowser.js"></script>
</head>
<body>
  <div id=box></div>
  <table align=center width="95%">
  <!-- header -->
  <tr><td colspan=5>
    <table class=header width="100%"><tr>
      <td class=leftheader><div id=breadcrumb></div><div id=cover></div></td>
      <td class=rightheader>%flash_player%</td>
    </tr></table>
  </td></tr>
  <!-- end header -->
  <tr><td colspan=5 id=content>
    <div class=loading>initializing...</div>
  </td></tr>
  <tr><td></td></tr><tr><td colspan=5>
    <!-- footer -->
    <table class=footer width="100%"><tr><td>
      %searchfield%
      <form method=post action="" name=streamtype><span id=options></span></form>
    </td><td align=right>
      <a class=feet href="#" class=feet id=podcast>podcast</a>|
      <a class=feet href="javascript:showHelp()">hotkeys</a>|
      %admin%
      <span class=feet><a href="http://musicbrowser.sf.net/">musicbrowser.sf.net</a> v%version%</span>
    </td></tr></table>
    <!-- end footer -->
  </td></tr></table>
</body></html>