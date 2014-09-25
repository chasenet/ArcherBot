#!/usr/bin/php

<?php

require_once './ArcherBot.class.php';

if(count($argv) >= 2) {

    $archer = new \Pentest\ArcherBot\ArcherBot($argv[1]);

    echo $archer->generateReport();
}
