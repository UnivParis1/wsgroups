<?php

$html_file = $_SERVER["DOCUMENT_ROOT"] . preg_replace("/\.html\.js$/", ".html", $_SERVER["REQUEST_URI"]);
echo "document.write(" . json_encode(file_get_contents($html_file)) . ");";
