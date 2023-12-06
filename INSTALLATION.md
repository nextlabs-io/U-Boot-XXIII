## Copy files to a web server. Point document root to /public folder

## Open data/parser/config/config.xml file and edit settings.

### General settings, modify those fields according to your requirements
```bash
        <activeConnections>20</activeConnections>
        <productSyncLimit>3</productSyncLimit>
        <productSyncDelay><![CDATA[-3 hours]]></productSyncDelay>
```
activeConnections : indicates how many simultaneous proxy connections allowed
productSyncLimit : how many products to sync per a single cron run
productSyncDelay : for how long the product is treated as up to date.

### Setup captcha account settings if needed
```bash
    <captcha>
        <!--
        deathbycaptcha login pass
        -->
        <login></login>
        <password></password>
        <solve_captcha>1</solve_captcha>
        <data_dir>data/parser/Captcha</data_dir>
    </captcha>
```

## Modify mysql access credentials in the file
```bash
config/autoload/local.php
```
```bash
    'db' => [
        'dsn'            => 'mysql:dbname=YOUR_DB_NAME;host=localhost;',
        'username' => 'YOUR_USER',
        'password' => 'YOUR_PASS',
    ],
```
## Run proxy load command (to load proxy settings from config.xml to settings)
```bash    
    http://parserWebUrl/parser/proxyconfig
```
It should state something like: XX were found and updated
  
## Test parser work by opening url like
```bash    
    http://parserWebUrl/parser/parse?asin=B017VQDXAE&locale=fr&mode=array
```
It should show relevant data to the asin

## 7. Import product list using this url:
```bash
    http://parserWebUrl/manager/list
```
## 8. Test parser sync work by opening url like
```bash
    http://parserWebUrl/parser/sync
```
it should show asins which were synced (if they are in the database)

## 9. setup cron jobs
```bash
* * * * * curl --silent http://parserWebUrl/parser/sync
# opt a means sleep in seconds before start, you need it 
#if you wish to run several processes per a minute.
* * * * * curl --silent http://parserWebUrl/parser/sync?a=2
*/30 * * * * curl --silent http://parserWebUrl/parser/clean
#check for proxyscraper and file for fresh proxies.
*/15 * * * * curl --silent http://parserWebUrl/parser/proxy
```

## 10. manage migrations

migrations are implemented using multi project yii2 migration engine
each module has a controllerMap config section where migration settings may be specified. Check Parser module for example.
```php yii migrate-parser``` to check parser migrations status
```php yii migrate-parser --interactive=0``` to run it automatically

### example create migration
```
php yii migrate-parser/create add_position_column_to_product_table --fields=product_page_price:float:null,offer_page_price:float:null
```

```
yii migrate-parser/create create_post_table --fields="author_id:integer:notNull:foreignKey(user),category_id:integer:defaultValue(1):foreignKey,title:string,body:text"
```

### remove latest migration
```yii migrate-parser/down 1```


### magmi cron command, store id is required
```shell
* * * * * wget -q -O /dev/null -o /dev/null   'https://parserUrl/magento/magmiUpdate?key=1&store=1&items_qty=1000
```

### magento updateDesc/Create/Delete cron command (store and type is optional)
```shell
* * * * * wget -q -O /dev/null -o /dev/null   'http://parserUrl/magento/processrequests?key=1&store=1&type=3'
```

### centric api usage cron command
```bash
* * * * * wget -q -O /dev/null -o /dev/null http://parserWebUrl/cron/centric
```
### amazon search cron command
http://parserWebUrl/crawler/search

### magento store url should be like this 
https://yourdomain/index.php/amazonimportproducts/index/parser


### added console commands, most cron scripts should be triggered from console.

