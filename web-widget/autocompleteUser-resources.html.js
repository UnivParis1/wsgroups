<?php // -*- php -*-

$base = "//wsgroups-test.univ-paris1.fr/web-widget";
$html = "
<link rel='stylesheet' href='$base/jquery-ui.css' type='text/css' />
<link rel='stylesheet' href='$base/ui.theme.css' type='text/css' />
<link rel='stylesheet' href='$base/autocompleteUser.css' type='text/css' />
";
echo "document.write(" . json_encode($html) . ");\n";

include "jquery-1.7.2.min.js";
include "jquery-ui-1.8.21.custom.min.js";
include "autocompleteUser.js";
