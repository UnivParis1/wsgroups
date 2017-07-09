<?php // -*- php -*-

require "../autocompleteUser-resources.html.js";
      
$html = '
<link rel="stylesheet" href="//wsgroups-test.univ-paris1.fr/web-widget/userinfo/userinfo.css" type="text/css" media="all" />
';               
echo "document.write(" . json_encode($html) . ");\n";

include "userinfo.js";
