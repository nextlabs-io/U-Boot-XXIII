php yii migrate-comparator  --interactive=0

php yii migrate-comparator/create edit_comparator_product_table_fields
- cron command
- as many as required
curl parserUrl/comparator-console/scrape
curl parserUrl/comparator-console/scrapeAmazon
or console command
php path/to/public/index.php scrape comparator
php path/to/public/index.php scrape comparatorAmazon

- comparator is using selenium webdriver to obtain content, simple curl or phantom browser would not work
in order to install selenium
apt install python-pip
apt install chromium-browser
pip install selenium
pip install fake_useragent

test the code by running (note, proxy settings has to be updated in the file)
python phantom/driver.py





