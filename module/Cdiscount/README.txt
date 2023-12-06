php yii migrate-cdiscount  --interactive=0

php yii migrate-cdiscount/create edit_cdiscount_product_table_fields
- cron command
- as many as required
curl parserUrl/cdiscount-console/scrape
curl parserUrl/cdiscount-console/scrapeAmazon
or console command
php path/to/public/index.php scrape cdiscount
php path/to/public/index.php scrape cdiscountAmazon

- cdiscount is using selenium webdriver to obtain content, simple curl or phantom browser would not work
in order to install selenium
apt install python-pip
apt install chromium-browser
pip install selenium
pip install fake_useragent

test the code by running (note, proxy settings has to be updated in the file)
python phantom/driver.py





