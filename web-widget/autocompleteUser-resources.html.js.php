<?php // -*- php -*-

$base = "//wsgroups-test.univ-paris1.fr/web-widget";

function loadCSS_urls($urls) {
   $loadCSS = '';
   foreach ($urls as $url) $loadCSS .= "    loadCSS('$url');\n";
   print "
(function() {
    function loadCSS(url) {
      var elt = document.createElement('link');
      elt.setAttribute('rel', 'stylesheet');
      elt.setAttribute('type', 'text/css');
      elt.setAttribute('href', url);
      document.head.appendChild(elt);
    }
$loadCSS
})();";
}
loadCSS_urls([ "$base/autocompleteUser.css" ]);

include "jquery-1.7.2.min.js";
include "kraaden.github.io-autocomplete.js";
include "autocompleteUser.js";
