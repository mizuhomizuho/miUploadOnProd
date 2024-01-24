<?php

$conf = include __DIR__ .'/.config.php';

if ($_POST['key'] !== $conf['key']) {

    return;
}

$res = [];

if (isset($_POST['fileGetContent'])) {

    $res['fileGetContent'] = [];

    foreach ($_POST['fileGetContent'] as $item) {

        $file = __DIR__ . $conf['baseRoot'] . '/' . $item['file'];

        $resEl = [];

        $resEl['file'] = $item['file'];

        if (!file_exists($file)) {

            $resEl['noFileExists'] = true;
        }
        else {

            $resEl['content'] = file_get_contents($file);
        }

        $res['fileGetContent'][] = $resEl;
    }
}

if (isset($_POST['filePutContent'])) {

    $res['filePutContent'] = [];

    foreach ($_POST['filePutContent'] as $fileV) {

        $file = __DIR__ . $conf['baseRoot'] . '/' . $fileV['file'];

        $fpcRes = [];

        if (!file_exists($file)) {

            $fpcRes['noFileExists'] = true;
        }

        if (!file_exists(dirname($file))) {

            mkdir(dirname($file), 0777, true);
        }

        $fpcRes['file'] = $fileV['file'];

        $fpcRes['res'] = file_put_contents($file, $fileV['content']);

        $res['filePutContent'][] = $fpcRes;
    }
}

echo json_encode($res);