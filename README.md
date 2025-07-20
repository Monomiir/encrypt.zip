# encrypt.zip
encrypt.zip - Online Text Encrypter using AES and RSA base.

# Installation
1. All files are in your root path (ex : /var/www/html)
2. Add crontab -e as below
```
#Purge expired messages at 3 o'clock
0 3 * * * php /your/data/path/gc.php msg
#Deactivate rate limit every 30 minutes
*/30 * * * * php /your/data/path/gc.php rate
```
3. (Optional) Make directories with permission 0700
```
sudo mkdir -m 700 messages
sudo mkdir -m 700 rate_limit
```
