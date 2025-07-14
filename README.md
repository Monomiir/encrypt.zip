# encrypt.zip
encrypt.zip - Online Text Encrypter using AES and RSA base

# Installation
1. All files are in your root path (ex : /var/www/html)
2. Add crontab -e as below
```
#Purge expired messages at 3 o'clock
0 3 * * * php /data/yourls/data/gc.php msg
#Deactivate rate limit every 30 minutes
*/30 * * * * php /data/yourls/data/gc.php rate
```
