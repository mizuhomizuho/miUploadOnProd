# miUploadOnProd

Such situation. I do not have access to update Production from Master. But everything has to be updated. The script can download updates starting from a specific commit.

```bash
php -f run.php help
php -f run.php show-files
php -f run.php backup
php -f run.php push
php -f run.php push from-commit 8a8b8c8d8
php -f run.php fc 78673180ee0fa0 bu sf

# Most often I use it like this
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