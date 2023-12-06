php yii migrate-bestbuy  --interactive=0

php yii migrate-bestbuy/create add_marketplace_product_table
php yii migrate-bestbuy/create add_product_table_fields_bb
php yii migrate-bestbuy/create add_special_status_to_best_buy_product_table
php yii migrate-bestbuy/create add_product_keepa_table
php yii migrate-bestbuy/create edit_broken_keepa_Elements
php yii migrate-bestbuy/create add_product_keepa_data_table
- cron command
- as many as required
curl parserUrl/bestbuy/scrape

- probably once a minute
curl parserUrl/bestbuy/scrapekeepa
