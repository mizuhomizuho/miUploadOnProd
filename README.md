# miUploadOnProd

Такая ситуация. Доступа к обновлению прода из мастера у меня нету. Но обновлять все ровно приходится.

```bash
php -f run.php help
php -f run.php show-files
php -f run.php backup
php -f run.php push
php -f run.php push from-commit 8a8b8c8d8
```