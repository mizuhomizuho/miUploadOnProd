<?php

/**
 * php -f run.php help
 */

$conf = include __DIR__ .'/../.uploadOnProd.config.php';

return [
    'key' => $conf['key'],
    'serverFileUrl' => $conf['serverFileUrl'],
    'baseRoot' => $conf['baseRoot'],
    'noGitFiles' => $conf['noGitFiles'],
];
