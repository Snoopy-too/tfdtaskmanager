<?php
declare(strict_types=1);

$content = file_get_contents('https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.js');
if ($content === false) {
    echo "Failed to fetch Fabric.js source.\n";
    exit(1);
}

$pos = strpos($content, '_setTextStyles: function');
if ($pos !== false) {
    echo substr($content, $pos, 800) . "\n";
}
