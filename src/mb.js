"use strict";

var currentFolder = '';
var currentHash = '###';
var prefix = 'mb.php/';
var hotkeyModifier = false;
var hotkeysDisabled = false;
var flashWidth = 500;
var flashHeight = 150;
var boxTimeout;
var streamType = 'flash';
var shuffle = false;

document.onkeydown = hotkey;

window.onload = function() {
  pollHash();
  setInterval(pollHash, 1000);
}

/**
 * Poll the url hash regularly for changes.
 */
var pollHash = function() {
  var locationHash = window.location.hash.replace(/^#/, '');
  if (locationHash == currentHash) {
    return; // Nothing's changed since last polled. 
  }

  // Firefox and Safari don't agree on hash encoding
  if (decodeURIComponent(currentHash) == locationHash) {
    return;
  }
  
  currentHash = locationHash;
  switch (currentHash.substr(0, 2)) {
      case 'p=':
        updateDirectory(currentHash.replace(/^p=/, ''));
        break;
      case 's=':
        search(currentHash.replace(/^s=/, ''));
        break;
      default:
        updateDirectory('');
  }
}

/**
 * Change to previous directory.
 */
var previousDir = function() {
  history.go(-1);
}

/**
 * Change directory.
 */
var changeDir = function(path) {
  updateDirectory(path);
  updateHash('p', path);
}

/**
 * Update content tag with specified content from specified path.
 */
var updateDirectory = function(path) {
  $('#content').html("<div class=loading>loading...</div>");
  currentFolder = path;
  fetchContent(path.replace('&', '%26'));
  $('#podcast').attr('href', prefix + path + '&stream=rss');
  $('#podcast').attr('title', prefix + path.replace(/\+/g, ' ') + ' podcast');
  $('#podcast').html('podcast');
}

/**
 * Set stream type.
 */
var setStreamType = function(path, streamType) {
  fetchContent(path.replace('&', '%26') +  '&streamType=' + streamType);
  
}

/**
 * Enable/disable shuffle.
 */
var setShuffle = function() {
  shuffle = $('#shuffle').prop('checked');
}

/**
 * HTTP GET content from path.
 */
var fetchContent = function(path) {  
  var http = httpGet(prefix + path + ".json");
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (!result) {
        $('#content').html("<div class=error>Error.</div>");
      } else {
        document.title = path;
        $('#cover').html(createCoverImage(result.shift()));
        $('#breadcrumb').html(createBreadcrumbs(path));
        //$('#options').html(result.options);
        var contentDiv = $('#content');
        contentDiv.html('');
        var table = createHtml(result);
        table.appendTo(contentDiv);

        if (streamType == 'flash' && $('#player').html() == "") {
           jwplayer("player").setup({
             'flashplayer': "jwplayer.swf",
             'height': flashHeight,
             'width': flashWidth,
             'playlist.position': 'bottom',
             'listbar.position': 'bottom',
             'listbar.size': flashHeight
           });          
        } else if ($('#batplay') == null && streamType == 'native') {
          $('#player').html('<div></div>');
        }
      }
    }
  }
  http.send(null);
}

var createBreadcrumbs = function(path) {
  var folders = path.split("/");
  var result = '<a href="#">Music</a>';
  for (var i = 0; i < folders.length; i++) {
    if (folders[i]) {
      result += ' » <a href="#p=' + folders[i] + '">' + folders[i] + '</a>';
    }
  }
  return result;
}

var createCoverImage = function(image) {
  if (!image) {
    return "";
  }
  return '<a href="javascript:showCover(\'' + image + '\')">' +
         '<img width="100" height="100" border="0" align="left" src="' + image + '" alt="" title="View enlarged cover"></a>';
}

var createHtml = function(entries) {
  var table = $('<table>').attr('width', '100%');
  table.append($('<thead>')).append($('<tbody>'));

  var counter = 0;
  var tr;
  var rowclass;
  for (var i = 0; i < entries.length; i++) {
    if (counter === 0) {
      if (rowclass === 'even') {
        rowclass = 'odd';
      } else {
        rowclass = 'even';
      }
      tr = $('<tr>').addClass(rowclass).appendTo(table);
    }
    tr.append(createTd(entries[i]).addClass('cell'));

    counter++;
    if (counter === 5) {
      counter = 0;
    }
  }

  return table;
}

var createTd = function(value) {
    if (value === null || value === 'null') {
        value = '';
    }
    var displayValue = value;
    var elements = value.replace(/\/$/, '').split('/');
    console.debug(elements);
    if (elements.length > 1) {
      displayValue = elements[elements.length - 1];
    }
    if (value.charAt(value.length-1) === "/") {
      return $('<td>').html(
        '<a href="javascript:play(\'' + value + '\')"><img border="0" alt="[Play]" title="Play this folder" src="play.gif"></img></a>' +
        '<a class="folder" href="javascript:changeDir(\'' + value + '\')" title="' + displayValue + '">&nbsp;' + displayValue + '</a>');
    }
    return $('<td>').html(
      '<a href="' + value + '"><img border="0" alt="[Download]" title="Download this song" src="download.gif"></img></a>' +
      '<a class="file" href="javascript:play(\'' + value + '\')" title="Play this song">&nbsp;' + displayValue + '</a>');
};


/**
 * HTTP GET.
 * @return HTTP object
 */
var httpGet = function(fullPath) {
  var http = false;
  if (navigator.appName.indexOf('Microsoft') != -1) {
    http = new ActiveXObject("Microsoft.XMLHTTP");
  } else {
    http = new XMLHttpRequest();
  }
  http.open("GET", fullPath, true);
  return http;
}

/**
 * Enable search field, disable global hotkeys.
 */
var enableSearch = function() {
  hotkeysDisabled = true;
  $('#search').val('');
}

/**
 * Disable search field, enable global hotkeys.
 */
 var disableSearch = function() {
  hotkeysDisabled = false;
  $('#search').val('search');
}

/**
 * Invoke search on keypress==return.
 */
var invokeSearch = function(e) {
  if (getKeyNum(e) == 13) {
    search();
  }
}

/**
 * Search for file or folder.
 * @param needle Use this needle.  Uses search field from page if empty.
 */
var search = function(needle) {
  var encodedNeedle = needle;
  if (!needle) {
    needle = $('#search').val();
    // Firefox and Safari don't agree on hash encoding
    encodedNeedle = encodeURIComponent(needle);
  }
  if (needle.length < 2) {
    showBox('<div class=error>Search term must be longer than 2 characters</div>', 3000);
    return;
  }
  updateHash('s', encodedNeedle);

  var startTime = new Date().getSeconds();
  showBox('<div class=error>Searching...</div>');
  var http = httpGet(prefix + "&search=" + needle);
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result && result.error) {
        showBox('<div class=error>' + result.error + '</div>');
      } else if (result) {
        if (result.numresults > 0) {
          document.title = result.title;
          $('#content').html(result.content);
          $('#breadcrumb').html(result.breadcrumb);
          $('#cover').html('');

          // I should enable options and podcasts for searches as well:
          $('#options').html('');
          $('#podcast').attr('href', '#');
          $('#podcast').attr('title', '');
          $('#podcast').html('');
        }
        var seconds = new Date().getSeconds() - startTime;
        showBox('Found ' + result.numresults + ' results in ' + seconds + ' seconds.', 3000);
      }
    }
  }
  http.send(null);
}

/**
 * Update hash in url field.
 * @param func Function, either 's' (search) or 'p' (path)
 * @param content Value in hash
 */
var updateHash = function(func, content) {
  if (content) {
    var tempHash = func + '=' + content;
    // Firefox and Safari don't agree on hash encoding
    if (encodeURIComponent(tempHash) == currentHash) {
      return;
    }
    currentHash = tempHash;
    window.location.hash = '#' + currentHash;
  } else {
    currentHash = '';
    window.location.hash = '#';
  }
}

/**
 * Rebuild search database.
 */
var buildDB = function() {
  var answer = confirm("Are you sure you want to rebuild the search database?");
  if (!answer) {
    showBox('<div class=error>Aborted...</div>', 3000);
    return; 
  }
  showBox('Building search DB...');
  var http = httpGet(prefix + "&builddb");
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result) {
        showBox('<div class=error>' + result.error + '</div>', 3000);
      }
    }
  }
  http.send(null);
}

/**
 * Show album cover.
 */
var showCover = function(picture) {
  showBox('<img alt="" border=0 src="' + picture + '">');  
}

/**
 * Show flash player hotkeys.
 */
var showHelp = function() {
  showBox('Flash player hotkeys<br>'
      + '<b>p</b> - play or pause<br>'
      + '<b>b</b> - skip back<br>'
      + '<b>n</b> - skip next<br>'
      + '<b>a</b> - play everything in this folder<br>');  
}

/**
 * Show the dialogue.
 */
var showBox = function(content, timeout) {
  $('#box').html('<a class=boxbutton href="javascript:hideBox()">×</a><div class=box>' + content + '</div>');
  if (timeout) {
    clearTimeout(boxTimeout);
    boxTimeout = setTimeout(hideBox, timeout);
  }
}

/**
 * Hide the dialogue.
 * @see showBox()
 */
var hideBox = function() {
  $('#box').html('');
}

/**
 * @return keynum from keypress
 */
var getKeyNum = function(e) {
  var keynum;
  if (window.event) { // IE
    keynum = e.keyCode;
  } else if (e.which) { // Netscape/Firefox/Opera
    keynum = e.which;
  }
  return keynum;
}

/**
 * Execute hotkey for flash player.
 */
var hotkey = function(e) {
  if (hotkeysDisabled) {
    return;
  }
  var keynum = getKeyNum(e);

  if (keynum == 224 || keynum == 16 || keynum == 17 || keynum == 18) {
    hotkeyModifier = true; // cmd, shift, ctrl, alt
  } else if (hotkeyModifier == true ) {
    hotkeyModifier = false; // modifier has been pressed
  } else {
    if (keynum == 80) jwplayer('player').pause(); // 'p'
    if (keynum == 66) jwplayer('player').playlistPrev(); // 'b'
    if (keynum == 78) jwplayer('player').playlistNext(); // 'n'
    if (keynum == 65) { // 'a'
      jwPlay(currentFolder, 'false');
      showBox("Playing all files in this folder", 3000);
    }
  }
}

/**
 * Crude json evaluator.  Returns false if input isn't json.
 */
var jsonEval = function(text) {
  if (text.substr(0, 1) != '[') {
    showBox('<div class=error>Could not parse content.<br>'
      + escapeHTML(text.substr(0,180)) + '...</div>');
    return false;
  } else {
    return eval("(" + text + ")");
  }
}

/**
 * Escape HTML string.
 */
var escapeHTML = function(str) {
   var div = document.createElement('div');
   var text = document.createTextNode(str);
   div.appendChild(text);
   return div.innerHTML;
}

/**
 * Play specified file or folder in the Flash Player.
 */
var jwPlay = function(path, shuffle) {
  jwplayer('player').load(path);
  jwplayer('player').play();
}

/**
 * @return A JW Flash Player SWF object
 */
var jwObject = function() {
  var so = new SWFObject('mediaplayer.swf', jwPlayerId, flashWidth, flashHeight, '8', "#FFFFFF");
  so.addParam('allowscriptaccess', 'always');
  so.addParam('allowfullscreen', 'false');
  so.addVariable('height', flashHeight);
  so.addVariable('width', flashWidth);
  so.addVariable('file', '');
  so.addVariable('displaywidth', '0');
  so.addVariable('showstop', 'true');
  so.addVariable('autostart', 'true');
  so.addVariable('usefullscreen', 'false');
  so.addVariable('shuffle', 'false');
  so.addVariable('enablejs', 'true');
  so.addVariable('type', 'mp3');
  so.addVariable('repeat', 'list');
  so.addVariable('thumbsinplaylist', 'false');
  return so;
}


/**
 * Play specified file or folder.
 */
var play = function(path) {
  
  if (streamType == 'flash') {
    jwPlay(path, shuffle);
    return;
  }
  var shuffleText = "";
  if (shuffle) { shuffleText = "&shuffle=true"; }
  var http = httpGet(prefix + path + shuffleText + "&stream=" + type);
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result.error) {
        showBox('<div class=error>' + result.error + '</div>', 5000);
      } else if (result && type == 'native') {
        batPlay(result.title, result.url);
      }
    }
  }
  http.send(null);
}

/**
 * Modified version of Batmosphere Embedded Media Player, version 2006-05-31
 * Written by David Battino, www.batmosphere.com
 * OK to use if this notice is included
 */
var batPlay = function(title, url) {
  var objType = "audio/mpeg";  // The MIME type for Macs and Linux
  if (navigator.userAgent.toLowerCase().indexOf("windows") != -1) {
    objType = "application/x-mplayer2"; // The MIME type to load the WMP plugin in non-IE browsers on Windows
  }
  var player = "<div id='batplay'>Playing " + title + "<br>"
   + "<object width='" + flashWidth + "' height='100'><param name='type' value='" + objType + "'>"
   + "<param name='src' value='" + url + "'><param name='autostart' value='0'>"
   + "<param name='showcontrols' value='1'><param name='showstatusbar' value='1'>"
   + "<embed src ='" + url + "' type='" + objType + "' autoplay='true' autostart='1' width='" + flashWidth
   + "' bgcolor='#ffffff' height='100' controller='1' showstatusbar='1'></embed>"
   + "</object></div>";

  $('#player').html(player);
}


