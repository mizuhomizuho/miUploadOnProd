<?php

/**
 * php -f run.php help
 */

$conf = include __DIR__ .'/../.uploadOnProd.config.php';

return [
    'key' => $conf['key'],
    'serverFileUrl' => $conf['serverFileUrl'],
    'baseRoot' => '/../../..',
    'buDir' => '/../../../../uploadOnProdBu',
    'noGitFiles' => [
        'htdocs/.__DEV__/.PROD.test.php',
    ],
];
