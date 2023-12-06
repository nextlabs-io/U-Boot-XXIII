# parser

General Parser 



#### Download csv with products sample
```
/manager/list?download_csv=1&check_all=1&format=csv&fields=asin,locale
```


#### check stock strings among all products (options are not mandatory, default locale ca, stockSelect=0)
/manager/stockpattern?locale=ca&stockSelect=0


#### check delivery strings among all products compared to prefered country options (options are not mandatory, default locale ca)
/manager/deliverypattern?locale=ca

#### get proxies from apis 
- enableExisting defaults to 0, if set - proxies which will be taken from API will be enabled in case if they are already in the database

/parser/proxy?enableExisting=1


### adding console cron commands
- example:
- curl
    /crawler/scrape
- console
    php public/index.php scrape catalog [--verbose|-v] 
