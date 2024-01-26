<?php

namespace Mi\Tools;

class UploadOnProd
{
    private const FLAGS = [
        'isPush' => [
            'push',
            'p',
        ],
        'isBackup' => [
            'backup',
            'bu',
            'b',
        ],
        'isNoBackup' => [
            'no-backup',
            'nbu',
            'nb',
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
    private bool $isNoGitFilesRun = false;

    function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->conf = include __DIR__ .'/.config.php';
        $this->startTime = date('Y-m-d H-i-s');

        if ($this->getFlag('fromCommit')) {
            foreach ($argv as $argvK => $argvV) {
                if (
                    in_array($argvV, $this::FLAGS['fromCommit'])
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
            if (in_array($argvV, $this::FLAGS[$key])) {
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
        $shellExecRes = shell_exec('cd "'
            . __DIR__ . $this->conf['baseRoot']
            . '" && git status --short');

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

            if (in_array($matchItem['type'], ['M', 'D'])) {

                $fileItem = [];
                $fileItem['type'] = $matchItem['type'];
                $fileItem['file'] = $matchItem['val'];
                $files[] = $fileItem;
            }
            elseif ($matchItem['type'] === '??') {

                $path = __DIR__ . $this->conf['baseRoot'] . '/' . $matchItem['val'];

                if (is_dir($path)) {

                    $path = preg_replace('/\/$/', '', $path);

                    foreach ($this->getFilesFromDir($path) as $file) {

                        $fileItem = [];
                        $fileItem['type'] = 'A';
                        $fileItem['file'] = mb_substr($file, mb_strlen(__DIR__ . $this->conf['baseRoot'] . '/'));
                        $files[] = $fileItem;
                    }
                }
                else {

                    $fileItem = [];
                    $fileItem['type'] = 'A';
                    $fileItem['file'] = $matchItem['val'];
                    $files[] = $fileItem;
                }
            }
            elseif ($matchItem['type'] === 'R') {

                $valExpl = explode(' -> ', $matchItem['val']);

                if (count($valExpl) !== 2) {
                    return false;
                }

                $fileItem = [];
                $fileItem['type'] = 'D';
                $fileItem['file'] = $valExpl[0];
                $files[] = $fileItem;

                $fileItem = [];
                $fileItem['type'] = 'A';
                $fileItem['file'] = $valExpl[1];
                $files[] = $fileItem;
            }
        }

        return $files;
    }

    private function getFilesFromCommit(): false|array
    {
        $gitDiffTreeRes = shell_exec('cd "'
            . __DIR__ . $this->conf['baseRoot']
            . '" && git diff-tree --no-commit-id ' . $this->currentCommit['id'] . ' -r'
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

        $path = __DIR__ . $this->conf['buDir'] . '/'
            . $this->startTime
            . ' ' . implode(' ', $argvParams);

        if ($this->currentCommit) {
            return $path . '/'
                . date('Y-m-d H-i-s', strtotime($this->currentCommit['date']))
                . ' ' . mb_substr($this->currentCommit['id'], 0, 8);
        }

        if ($this->isNoGitFilesRun) {
            return $path . '/noGitFiles';
        }

        return $path;
    }

    private function backup(array $files): bool|int
    {
        $buCount = 0;
        $skipNoNeedBuCount = 0;

        $filesForBu = [];
        foreach ($files as $file) {
            $filesForBu[] = [
                'file' => $file['file'],
            ];
        }

        $curlRes = $this->curl([
            'fileGetContent' => $filesForBu,
        ]);

        $curlResDecode = (array) json_decode($curlRes, true);

        if (count($curlResDecode['fileGetContent']) !== count($filesForBu)) {

            return false;
        }

        foreach ($curlResDecode['fileGetContent'] as $file) {

            if (
                isset($file['noFileExists'])
                && $file['noFileExists']
            ) {

                $skipNoNeedBuCount++;
                continue;
            }

            if (!isset($file['content'])) {

                return false;
            }

            $buFile = $this->getBuDir() . '/files/' . $file['file'];

            if (!file_exists(dirname($buFile))) {

                mkdir(dirname($buFile), 0777, true);
            }

            $fpcRes = file_put_contents($buFile, $file['content']);

            if ($fpcRes) {

                $buCount++;
            }
        }

        if ($buCount + $skipNoNeedBuCount !== count($files)) {

            return false;
        }

        return $buCount;
    }

    private function push(array $files): bool|array
    {
        $return = [];

        $forPush = [];

        $skipTypeD = [];

        foreach ($files as $filesV) {

            if (
                !$this->currentCommit
                && in_array($filesV['type'], ['M', 'A'])
                && !file_exists(__DIR__ . $this->conf['baseRoot'] . '/' . $filesV['file'])
            ) {
                return false;
            }

            if (in_array($filesV['type'], ['D'])) {
                $skipTypeD[] = $filesV['file'];
                continue;
            }

            $forPush[] = $filesV;
        }

        $return['filesCount'] = count($files);
        $return['forPushCount'] = count($forPush);

        if ($skipTypeD) {
            $return['skipTypeD'] = $skipTypeD;
        }

        $goodPused = 0;

        $fpcArr = [];
        foreach ($forPush as $forPushV) {
            $fpcArr[] = [
                'content' => file_get_contents(
                    __DIR__ . $this->conf['baseRoot'] . '/' . $forPushV['file']
                ),
                'file' => $forPushV['file'],
            ];
        }

        $curlResDecode = null;
        if ($fpcArr) {

            $curlRes = $this->curl([
                'filePutContent' => $fpcArr,
            ]);

            $curlResDecode = (array) json_decode($curlRes, true);
        }

        if (
            !$curlResDecode
            || count($curlResDecode['filePutContent']) !== count($fpcArr)
        ) {

            $return['error'][] = [
                'count($curlResDecode[filePutContent])' => count($curlResDecode['filePutContent']),
                'count($fpcArr)' => count($fpcArr),
            ];

            return $return;
        }

        foreach ($forPush as $forPushK => $forPushV) {

            $resItem = $curlResDecode['filePutContent'][$forPushK];

            if (
                isset($resItem['res'])
                && (
                    $resItem['res']
                    || $resItem['res'] === 0
                )
            ) {
                $goodPused++;
            }
            else {
                $return['errors'][] = [
                    'resItem' => $resItem,
                ];
            }

            if (
                in_array($forPushV['type'], ['M'])
                && isset($resItem['noFileExists'])
                && $resItem['noFileExists']
            ) {
                $return['warning'][] = [
                    'file' => $forPushV,
                    'noFileExists' => true,
                ];
            }
        }

        $return['goodPused'] = $goodPused;

        return $return;
    }

    private function getCurBranch(): string
    {
        $shellRes = shell_exec('cd "'
            . __DIR__ . $this->conf['baseRoot']
            . '" && git status');

        preg_match('/^On branch (?<curBranch>[^\n]+)\n/', $shellRes, $shellResMatch);

        if (!$shellResMatch) {

            trigger_error(__FUNCTION__, E_USER_ERROR);
        }

        return $shellResMatch['curBranch'];
    }

    private function isNoMyLastCommitInMaster(): false|array
    {
        $return = [];

        $curBranch = $this->getCurBranch();

        if ($curBranch === $this->conf['gitMasterBranchName']) {

            $return['err'][] = [
                'text' => 'On master branch',
            ];

            return $return;
        }

        $checkoutBackCmd = 'cd "'
            . __DIR__ . $this->conf['baseRoot']
            . '" && git checkout ' . $curBranch;

        $shellRes = shell_exec('cd "'
            . __DIR__ . $this->conf['baseRoot']
            . '" && git checkout ' . $this->conf['gitMasterBranchName'] .' && git pull && git log');

        if ($this->getCurBranch() !== $this->conf['gitMasterBranchName']) {

            $return['err'][] = [
                'text' => 'No master branch checkout',
            ];

            if ($this->getCurBranch() !== $curBranch) {
                shell_exec($checkoutBackCmd);
            }

            if ($this->getCurBranch() !== $curBranch) {
                $return['err'][] = [
                    'text' => 'Current branch ' . $this->getCurBranch(),
                ];
            }

            return $return;
        }

        preg_match('/\nAuthor: (?<lastAuthor>[^\n]+)\n/', $shellRes, $shellResMatch);

        if (!$shellResMatch) {

            trigger_error(__FUNCTION__, E_USER_ERROR);
        }

        if ($shellResMatch['lastAuthor'] === $this->conf['gitLogAuthor']) {

            shell_exec($checkoutBackCmd);

            if ($this->getCurBranch() !== $curBranch) {

                $return['err'][] = [
                    'text' => 'No checkout back',
                ];

                return $return;
            }

            return false;
        }

        return $return;
    }

    function run($echo = true): array
    {

        $return = [];

        if ($this->getFlag('isHelp')) {

            echo "\n" . json_encode($return,
                JSON_UNESCAPED_UNICODE
                |JSON_UNESCAPED_SLASHES
                |JSON_PRETTY_PRINT) . "\n\n";

            return $return;
        }

        if (($isNoMyLastCommitInMasterRes = $this->isNoMyLastCommitInMaster()) !== false) {

            $return = [
                'isNoMyLastCommitInMaster' => $isNoMyLastCommitInMasterRes,
            ];

            echo "\n" . json_encode($return,
                    JSON_UNESCAPED_UNICODE
                    |JSON_UNESCAPED_SLASHES
                    |JSON_PRETTY_PRINT) . "\n\n";

            return $return;
        }

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

            $shellExecRes = shell_exec('cd "'
                . __DIR__ . $this->conf['baseRoot']
                . '" && git log');

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

        if (
            isset($this->conf['noGitFiles'])
            && $this->conf['noGitFiles']
        ) {
            $this->isNoGitFilesRun = true;
            $runParams[] = ['isCommit' => false];
        }

        $runParams[] = ['isCommit' => false];

        foreach ($runParams as $runParamsV) {

            if (
                isset($runParamsV['isCommit'])
                && $runParamsV['isCommit']
            ) {
                $this->currentCommit = $runParamsV['commit'];
            }
            else{
                $this->currentCommit = null;
            }

            $res = $this->getRes();

            $this->isNoGitFilesRun = false;

            $return[] = $res;

            if (!$echo) {
                continue;
            }

            if ($this->getFlag('isShowFiles')) {

                if (
                    isset($res['noFiles'])
                    && $res['noFiles']
                ) {
                    echo "No files...\n";
                }
                else {

                    $forShow = [];

                    foreach ($res['files'] as $file) {

                        $forShow[] = $file['type'] . ' ' . $file['file'];
                    }

                    echo implode("\n", $forShow) . "\n";
                }
            }

            unset($res['files']);

            $isGoodPushRes = isset($res['pushRes'])
                && is_array($res['pushRes'])
                && count($res['pushRes']) === 3
                && isset($res['pushRes']['filesCount'])
                && isset($res['pushRes']['forPushCount'])
                && isset($res['pushRes']['goodPused'])
                && $res['pushRes']['goodPused']
                && $res['pushRes']['goodPused'] === $res['pushRes']['filesCount']
                && $res['pushRes']['goodPused'] === $res['pushRes']['forPushCount'];

            if (
                count($res) === 3
                && isset($res['isNoGitFiles'])
                && isset($res['isEnd'])
                && $res['isNoGitFiles']
                && $res['isEnd']
                && $isGoodPushRes
            ) {
                echo 'No git ' . $res['pushRes']['goodPused'];
            }
            elseif (
                count($res) === 2
                && isset($res['isEnd'])
                && $res['isEnd']
                && $isGoodPushRes
            ) {
                echo 'No commit ' . $res['pushRes']['goodPused'];
            }
            else {

                echo json_encode($res,
                    JSON_UNESCAPED_UNICODE
                    |JSON_UNESCAPED_SLASHES
                    |JSON_PRETTY_PRINT);
            }

            echo "\n";
        }

        return $return;
    }

    private function getRes(): array
    {
        $return = [];

        if ($this->currentCommit) {

            $files = $this->getFilesFromCommit();

            $return['commit'] = $this->currentCommit;
        }
        else {

            if ($this->isNoGitFilesRun) {

                $return['isNoGitFiles'] = true;

                $files = [];
                foreach ($this->conf['noGitFiles'] as $noGitFile) {
                    $files[] = [
                        'type' => 'noGitFile',
                        'file' => $noGitFile,
                    ];
                }
            }
            else{

                $files = $this->getNoCommitedFiles();
            }
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

            if (
                !isset($return['backupRes'])
                && !$this->getFlag('isNoBackup')
            ) {

                $bu();
            }

            $return['pushRes'] = false;

            if (
                (
                    isset($return['backupRes'])
                    && $return['backupRes']
                )
                || $this->getFlag('isNoBackup')
            ) {

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
