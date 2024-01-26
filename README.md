# miUploadOnProd

Такая ситуация. Доступа к обновлению прода из мастера у меня нету. Но обновлять все ровно приходится.
Скрипт проверяет что в мастере последний коммит твой, если так, грузит на прод.
Может сперва делать бэкап того что грузит.

```bash
php -f run.php help
php -f run.php show-files
php -f run.php backup
php -f run.php push
php -f run.php push from-commit 8a8b8c8d8
php -f run.php fc 78673180ee0fa0 bu sf

# Чаще всего я пользуюсь так
php -f run.php p nb
php -f run.php p
php -f run.php sf
```

```bash
php -f run.php help

{
    "isPush": [
        "push",
        "p"
    ],
    "isBackup": [
        "backup",
        "bu",
        "b"
    ],
    "isNoBackup": [
        "no-backup",
        "nbu",
        "nb"
    ],
    "fromCommit": [
        "from-commit",
        "fc"
    ],
    "isShowFiles": [
        "show-files",
        "sf"
    ],
    "isHelp": [
        "help",
        "man",
        "h",
        "m"
    ]
}
```