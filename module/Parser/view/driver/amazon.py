<?php
/** @var Parser\Model\Web\Selenium\SeleniumChromeDriverTemplate $dataObject */
?>
#!/usr/bin/env python
# -*- coding: utf-8 -*-

from selenium import webdriver
import time
import json
import sys
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import TimeoutException
from selenium.common.exceptions import WebDriverException
from fake_useragent import UserAgent


start_time = time.time()


pageUrl = "<?= trim($dataObject->url) ?>"



proxy = '<?php echo $dataObject->proxyType == 'socks5' ? 'socks5://' : ''?><?= $dataObject->proxy ?>'

proxyArg = '--proxy-server=%s' % proxy
ua = UserAgent()
userAgent = ua.random
result = {
"userAgent": userAgent,
"proxy" : proxyArg
}


# chrome_options
chrome_options = Options()
chrome_options.add_argument(proxyArg)
chrome_options.add_argument('--user-agent=%s' % userAgent)

chrome_options.add_argument('--headless')
chrome_options.add_argument('--no-sandbox')
chrome_options.add_argument('--disable-gpu')
chrome_options.add_argument('--window-size=1280x1696')
chrome_options.add_argument('--user-data-dir=/tmp')
chrome_options.add_argument('--hide-scrollbars')
chrome_options.add_argument('--enable-logging')
chrome_options.add_argument('--log-level=0')
chrome_options.add_argument('--v=99')
chrome_options.add_argument('--single-process')
chrome_options.add_argument('--data-path=/tmp')
chrome_options.add_argument('--ignore-certificate-errors')
chrome_options.add_argument('--homedir=/tmp')
chrome_options.add_argument('--disk-cache-dir=/tmp/cache-dir')


# start web browser
browser=webdriver.Chrome('<?= $dataObject->driverPath ?>', chrome_options = chrome_options)

# get source code
try:
    browser.get(pageUrl)
except WebDriverException as e:
    result['error'] = str(e)
except:
    result['error'] = "Unexpected error"




timeout = <?= $dataObject->eventTimeout ?>

result['result'] = "load success"

#try:
#    logo_present = EC.presence_of_element_located((By.ID, 'hFull'))
#    WebDriverWait(browser, timeout).until(logo_present)
#    result['result'] = "load success"
#except TimeoutException:
#    result['result'] = "Timed out waiting for event"

#browser.save_screenshot('screenie'+ iteration + '.png')
html = browser.page_source
result["content_length"] = len(html)
file = open('<?= $dataObject->contentFilePath ?>', 'w')
# python
#file.write(html.encode('utf-8').strip())
# python3
file.write(html)
file.close()

# close web browser
browser.close()
browser.quit()

result["execution_time"] = (time.time() - start_time)

pretty_data = json.dumps(result, indent=4)
print(pretty_data)

