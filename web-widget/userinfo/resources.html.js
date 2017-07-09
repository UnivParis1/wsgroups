<?php // -*- php -*-

require "../autocompleteUser-resources.html.js";
      
$html = "
<link rel='stylesheet' href='$base/userinfo/userinfo.css' type='text/css' />
";               
echo "document.write(" . json_encode($html) . ");\n";

include "userinfo.js";
