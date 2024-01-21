<?php

$conf = include __DIR__ .'/.config.php';

if ($_POST['key'] !== $conf['key']) {
    return;
}

$res = [];

if (!isset($_POST['file'])) {
    return;
}

if (isset($_POST['filePutContent'])) {
    $res['filePutContent'] = [
        'res' => file_put_contents(__DIR__ . $conf['baseRoot'] . '/' . $_POST['file'], $_POST['filePutContent']),
    ];
}

if (isset($_POST['fileGetContent'])) {
    if (!file_exists(__DIR__ . $conf['baseRoot'] . '/' . $_POST['file'])) {
        $res['fileGetContent'] = [
            'noFileExists' => true,
        ];
    }
    else {
        $res['fileGetContent'] = [
            'res' => file_get_contents(__DIR__ . $conf['baseRoot'] . '/' . $_POST['file']),
        ];
    }
}

echo json_encode($res);