Installation
------------
Replace the regular template.inc with this one.

Implementation notes
--------------------
- The comment on line 1 triggers quirksmode.  It's needed for a workaround for displaying fixed elements in IE5 and IE6
- A global font style is defined in "body" because IE6 would use different font styles for breadcrumbs, table contents and footer
- margin-right in div#leftheader keeps the breadcrumb from disappearing behind the player
- height in div#leftheader is needed in combination with overflow: auto, which is needed to keep the player from being placed over the 
  albumlist/albumtracks (becoming not clickable) when to long path pushed the player down
- padding: 0px in div#leftheader is needed because of overflow scrollbars getting this amount of pixels to high in firefox
  instead defined as margin for #breadcrumbs and #cover 
- height: 150px in div#rightheader is needed because otherwise the player gets some extra space below which causes a scrollbar ; set at same height as body padding top 
- Thanks to http://tagsoup.com/cookbook/css/fixed/#gtev5 for IE6 workarounds

Credits
-------
CSS template by Henrik Brautaset Aronsen and Henrie Van Der Locht
