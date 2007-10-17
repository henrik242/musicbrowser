<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <title>Music browser</title>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <style type="text/css">
<!-- 
.topcolor { background-color: #EEEEBB; }
TH { background-color: #DDDDDD; }
.selector { font-size: 0.9em;  background-color: #DDDDDD; }
.error { color: red; }
-->
  </style>
</head>
<body>
  <p><div class=error>%error_msg%</div>
  <table align=center width="95%">
  <tr><td align=center colspan=%columns% class="topcolor">
    %cover_image%
    <br><p>%top_path%</p><br>
  </td></tr>
    %content%
  <tr><td>&nbsp;</td></tr>
  <tr><td colspan=%columns% class=selector align=center>
    <form action="%folder_path%" method="post" name="streamtype">

    <input type="radio" name="streamtype" value="m3u" onClick="document.streamtype.submit()" %m3u_checked%>m3u&nbsp;
    <input type="radio" name="streamtype" value="pls" onClick="document.streamtype.submit()" %pls_checked%>pls
    </form>
  </td></tr></table>
</body></html>