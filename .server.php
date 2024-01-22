<?php

$conf = include __DIR__ .'/.config.php';

if ($_POST['key'] !== $conf['key']) {
    return;
}

$res = [];

if (!isset($_POST['file'])) {
    return;
}

$file = __DIR__ . $conf['baseRoot'] . '/' . $_POST['file'];

if (isset($_POST['fileGetContent'])) {
    if (!file_exists($file)) {
        $res['fileGetContent'] = [
            'noFileExists' => true,
        ];
    }
    else {
        $res['fileGetContent'] = [
            'res' => file_get_contents($file),
        ];
    }
}

if (isset($_POST['filePutContent'])) {
    $fpcRes = [];
    if (!file_exists($file)) {
        $fpcRes['noFileExists'] = true;
    }
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }
    $fpcRes['res'] = file_put_contents($file, $_POST['filePutContent']);
    $res['filePutContent'] = $fpcRes;
}

echo json_encode($res);