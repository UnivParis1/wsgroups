<?php // -*- php -*-

$html = '
<link rel="stylesheet" href="//wsgroups-test.univ-paris1.fr/web-widget/jquery-ui.css" type="text/css" media="all" />
<link rel="stylesheet" href="//wsgroups-test.univ-paris1.fr/web-widget/ui.theme.css" type="text/css" media="all" />
<link rel="stylesheet" href="//wsgroups-test.univ-paris1.fr/web-widget/autocompleteUser.css" type="text/css" media="all" />
';               
echo "document.write(" . json_encode($html) . ");";

include "jquery-1.7.2.min.js";
include "jquery-ui-1.8.21.custom.min.js";
include "autocompleteUser.js";
