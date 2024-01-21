<?php

namespace Mi\Tools;

class UploadOnProd
{
    const FLAGS = [
        'isPush' => [
            'push',
            'p',
        ],
        'isBackup' => [
            'backup',
            'bu',
            'b',
        ],
        'fromCommit' => [
            'from-commit',
            'fc',
        ],
        'isShowFiles' => [
            'show-files',
            'sf',
        ],
        'isHelp' => [
            'help',
            'man',
            'h',
            'm',
        ],
    ];

    private array $argv;
    private array $conf;
    private string $startTime;
    private ?string $fromCommit = null;
    private ?array $currentCommit = null;

    function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->conf = include __DIR__ .'/.config.php';
        $this->startTime = date('Y-m-d H-i-s');

        if ($this->getFlag('fromCommit')) {
            foreach ($argv as $argvK => $argvV) {
                if (
                    in_array($argvV, static::FLAGS['fromCommit'])
                    && isset($argv[$argvK + 1])
                ) {
                    $this->fromCommit = $argv[$argvK + 1];
                }
            }
        }
    }

    private function getFlag(string $key): bool
    {
        foreach ($this->argv as $argvV) {
            if (in_array($argvV, static::FLAGS[$key])) {
                return true;
            }
        }

        return false;
    }

    private function curl(array $params): string
    {
        $params['key'] = $this->conf['key'];

        $ch = curl_init($this->conf['serverFileUrl']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        return $res;
    }

    private function getFilesFromDir(string $dir): array {

        $files = [];

        if ($handle = opendir($dir)) {

            while (false !== ($entry = readdir($handle))) {

                if ($entry !== '.' && $entry !== '..') {

                    if (is_dir($dir . '/' . $entry)) {

                        $fns = __FUNCTION__;

                        foreach ($this->$fns($dir . '/' . $entry) as $file) {
                            $files[] = $file;
                        }
                    }
                    else {

                        $files[] = $dir . '/' . $entry;
                    }

                }

            }

            closedir($handle);

        }

        return $files;
    }

    private function getNoCommitedFiles(): false|array
    {
        $shellExecRes = shell_exec('git status --short');

        $files = [];

        $gitStatusShortResLines = explode("\n", $shellExecRes);

        foreach ($gitStatusShortResLines as $gitStatusShortResLinesV) {

            preg_match(
                '/^ ?(?<type>[A-Z]|\?\?) +(?<val>.+)$/',
                $gitStatusShortResLinesV,
                $matchItem
            );

            if (!$matchItem) {
                continue;
            }

            $fileItem = [];

            if (in_array($matchItem['type'], ['M', 'D'])) {

                $fileItem['type'] = $matchItem['type'];
                $fileItem['file'] = $matchItem['val'];
            }
            elseif ($matchItem['type'] === '??') {

                $path = __DIR__ . '/' . $matchItem['val'];

                if (is_dir($path)) {

                    $path = preg_replace('/\/$/', '', $path);

                    foreach ($this->getFilesFromDir($path) as $file) {

                        $fileItem['type'] = 'A';
                        $fileItem['file'] = mb_substr($file, mb_strlen(__DIR__ . '/'));
                    }
                }
                else {

                    $fileItem['type'] = 'A';
                    $fileItem['file'] = mb_substr($path, mb_strlen(__DIR__ . '/'));
                }
            }
            elseif ($matchItem['type'] === 'R') {

                $valExpl = explode(' -> ', $matchItem['val']);

                if (count($valExpl) !== 2) {
                    return false;
                }

                $fileItem['type'] = 'D';
                $fileItem['file'] = $valExpl[0];

                $fileItem['type'] = 'A';
                $fileItem['file'] = $valExpl[1];
            }

            if (!$fileItem) {

                return false;
            }

            $file = __DIR__  . '/' . $fileItem['file'];

            $fileItem['file'] = mb_substr(
                realpath(dirname($file)) . '/' . basename($file),
                mb_strlen(realpath(__DIR__ . $this->conf['baseRoot']))
            );

            $fileItem['file'] = preg_replace('/^\//', '', $fileItem['file']);

            $files[] = $fileItem;
        }

        return $files;
    }

    private function getFilesFromCommit(): false|array
    {
        $gitDiffTreeRes = shell_exec(
            'git diff-tree --no-commit-id ' . $this->currentCommit['id'] . ' -r'
        );

        $files = [];

        $gitDiffTreeResLines = explode("\n", $gitDiffTreeRes);

        $skipClearLine = 0;
        $skipIfNoExists = 0;

        foreach ($gitDiffTreeResLines as $gitDiffTreeResLinesV) {

            preg_match(
                '/^\:\d+ \d+ \w+ \w+ (?<type>[A-Z])[ \t]+(?<file>.+)$/',
                $gitDiffTreeResLinesV,
                $matchItem
            );

            if (!$matchItem) {
                $skipClearLine++;
                continue;
            }

            if (!file_exists(__DIR__ . $this->conf['baseRoot'] . '/' . $matchItem['file'])) {
                $skipIfNoExists++;
                continue;
            }

            $files[] = [
                'type' => $matchItem['type'],
                'file' => $matchItem['file'],
            ];
        }

        if (
            !$files
            || $skipClearLine !== 1
            || count($files) !== count($gitDiffTreeResLines) - $skipClearLine - $skipIfNoExists
        ) {
            return false;
        }

        return $files;
    }

    private function getBuDir(): string
    {
        $argvParams = $this->argv;
        unset($argvParams[0]);

        $fromLastCommit = $this->currentCommit
            ? '/'
                . date('Y-m-d H-i-s', strtotime($this->currentCommit['date']))
                . ' ' . mb_substr($this->currentCommit['id'], 0, 8)
            : '';

        return __DIR__ . '/bu/'
            . $this->startTime
            . ' ' . implode(' ', $argvParams)
            . $fromLastCommit;
    }

    private function backup(array $files): bool|int
    {
        $buCount = 0;
        $skipNoNeedBuCount = 0;

        foreach ($files as $file) {

            $curlRes = $this->curl([
                'fileGetContent' => true,
                'file' => $file['file'],
            ]);

            $curlResDecode = (array) json_decode($curlRes, true);

            if (!$curlResDecode) {

                return false;
            }

            if (
                isset($curlResDecode['fileGetContent']['noFileExists'])
                && $curlResDecode['fileGetContent']['noFileExists']
            ) {

                $skipNoNeedBuCount++;
                continue;
            }

            if (!isset($curlResDecode['fileGetContent']['res'])) {

                return false;
            }

            $buFile = $this->getBuDir() . '/files/' . $file['file'];

            if (!file_exists(dirname($buFile))) {

                mkdir(dirname($buFile), 0777, true);
            }

            $fpcRes = file_put_contents($buFile, $curlResDecode['fileGetContent']['res']);

            if ($fpcRes) {

                $buCount++;
            }
        }

        if ($buCount + $skipNoNeedBuCount !== count($files)) {

            return false;
        }

        return $buCount;
    }

    private function push(array $files): bool
    {

        return false;
    }

    function run($echo = true): array
    {
        if ($this->getFlag('isHelp')) {

            echo "\n" .var_export(static::FLAGS, true) . "\n\n";
            return [];
        }

        $return = [];

        if (
            $this->getFlag('fromCommit')
            && !$this->fromCommit
        ) {
            $return['badParams'] = true;
        }

        $commits = [];

        if (
            $this->getFlag('fromCommit')
            && !(
                isset($return['badParams'])
                && $return['badParams']
            )
        ) {

            $shellExecRes = shell_exec('git log');

            $shellExecRes = "\n" . $shellExecRes;

            $gitLogExpl = explode("\ncommit ", $shellExecRes);

            $commitFinded = false;
            foreach ($gitLogExpl as $gitLogExplV) {

                if ($gitLogExplV === '') {
                    continue;
                }

                $expl2 = explode("\nDate:   ", $gitLogExplV);

                if (count($expl2) !== 2) {
                    break;
                }

                preg_match(
                    '/^(?<commitId>\w+)[ \n]/',
                    $expl2[0],
                    $matchForCommitId
                );

                preg_match(
                    '/^(?<commitDate>[^\n]+)\n/',
                    $expl2[1],
                    $matchForCommitDate
                );

                if (!$matchForCommitId || !$matchForCommitDate) {
                    break;
                }

                $commits[] = [
                    'id' => $matchForCommitId['commitId'],
                    'date' => $matchForCommitDate['commitDate'],
                ];

                if (mb_substr($matchForCommitId['commitId'], 0, 8) === mb_substr($this->fromCommit, 0, 8)) {
                    $commitFinded = true;
                    break;
                }
            }

            if (!$commitFinded) {
                $return['badParams'] = true;
            }
        }

        if (
            isset($return['badParams'])
            && $return['badParams']
        ) {

            if ($echo) {
                echo "\n" . var_export($return, true) . "\n\n";
            }

            return $return;
        }

        $runParams = [];

        if ($commits) {

            foreach (array_reverse($commits) as $commitsV) {
                $runParams[] = [
                    'isCommit' => true,
                    'commit' => $commitsV,
                ];
            }
        }

        $runParams[] = ['isCommit' => false];

        $isFirstClearLineShowed = false;

        foreach ($runParams as $runParamsV) {

            if ($runParamsV['isCommit']) {
                $this->currentCommit = $runParamsV['commit'];
            }
            else{
                $this->currentCommit = null;
            }

            $res = $this->getRes();

            $return[] = $res;

            if (!$echo) {
                continue;
            }

            if (!$isFirstClearLineShowed) {
                echo "\n";
                $isFirstClearLineShowed = true;
            }

            if ($this->getFlag('isShowFiles')) {

                if (
                    isset($res['noFiles'])
                    && $res['noFiles']
                ) {
                    echo "No files...\n\n";
                }
                else {

                    $forShow = [];

                    foreach ($res['files'] as $file) {

                        $forShow[] = $file['type'] . ' ' . $file['file'];
                    }

                    echo implode("\n", $forShow) . "\n\n";
                }
            }

            unset($res['files']);

            echo var_export($res, true) . "\n\n";
        }

        return $return;
    }

    private function getRes($isFromLastCommit = false): array
    {
        $return = [];

        if ($this->currentCommit) {

            $files = $this->getFilesFromCommit();

            $return['commit'] = $this->currentCommit;
        }
        else {

            $files = $this->getNoCommitedFiles();
        }

        if (!$files) {

            return [
                'noFiles' => true,
            ];
        }

        $return['files'] = $files;

        $bu = function () use ($files, &$return) {

            $buRes = $this->backup($files);

            $return['backupRes'] = $buRes !== false;

            if ($return['backupRes']) {

                $return['backupCount'] = $buRes;
            }
        };

        if ($this->getFlag('isBackup')) {

            $bu();
        }

        if ($this->getFlag('isPush')) {

            if (!isset($return['backupRes'])) {

                $bu();
            }

            $return['pushRes'] = false;

            if ($return['backupRes']) {

                $return['pushRes'] = $this->push($files);
            }
        }

        $return['isEnd'] = true;

        if (isset($return['backupRes'])) {

            if (
                !isset($return['backupCount'])
                || !$return['backupCount']
            ) {
                mkdir($this->getBuDir(), 0777, true);
            }

            file_put_contents($this->getBuDir() . '/result.log', var_export($return, true));
        }

        return $return;
    }
}

(new UploadOnProd($argv))->run();
