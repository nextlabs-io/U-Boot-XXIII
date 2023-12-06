<?php
/** @var Parser\Model\Web\Selenium\SeleniumChromeDriverTemplate $dataObject */
?>
#!/usr/bin/env python
# -*- coding: utf-8 -*-

from selenium import webdriver
from selenium.webdriver.common.desired_capabilities import DesiredCapabilities
from selenium.webdriver import FirefoxOptions
from selenium.webdriver.common.proxy import *
import time
import json
from selenium.common.exceptions import TimeoutException
from selenium.common.exceptions import WebDriverException
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from fake_useragent import UserAgent


start_time = time.time()


pageUrl = "<?= trim($dataObject->url) ?>"




proxyPath = '<?= $dataObject->proxy ?>'


proxy = Proxy({
    'proxyType': ProxyType.MANUAL,
    'httpProxy': proxyPath,
    'ftpProxy': proxyPath,
    'sslProxy': proxyPath,
    'noProxy': '' # set this value as desired
    })

ua = UserAgent()
userAgent = ua.random
result = {
    "userAgent": userAgent,
    "proxy" : proxyPath
}


firefox_capabilities = DesiredCapabilities.FIREFOX
firefox_capabilities['marionette'] = True
#you probably don't need the next 3 lines they don't seem to work anyway
firefox_capabilities['handleAlerts'] = True
firefox_capabilities['acceptSslCerts'] = True
firefox_capabilities['acceptInsecureCerts'] = True

opts = FirefoxOptions()
opts.add_argument("--headless")


ffProfilePath = '/var/www/parser/html/phantom/FirefoxSeleniumProfile'
profile = webdriver.FirefoxProfile(profile_directory=ffProfilePath)

proxy_host = "<?= $dataObject->proxyHost ?>"
proxy_port = <?= $dataObject->proxyPort ?>


#proxy_host = "127.0.0.1"
#proxy_port = 9060

profile.set_preference("network.proxy.type", 1)

profile.set_preference("network.proxy.http", proxy_host)
profile.set_preference("network.proxy.http_port", proxy_port)
profile.set_preference("network.proxy.https", proxy_host)
profile.set_preference("network.proxy.https_port", proxy_port)
#profile.set_preference("network.proxy.ssl", proxy_host)
#profile.set_preference("network.proxy.ssl_port", proxy_port)
#profile.set_preference("network.proxy.ftp", proxy_host)
#profile.set_preference("network.proxy.ftp_port", proxy_port)
<?php if($dataObject->proxyType === 'socks5') : ?>
profile.set_preference("network.proxy.socks", proxy_host)
profile.set_preference("network.proxy.socks_port", proxy_port)
<?php endif; ?>
profile.update_preferences()


geckoPath = '<?= $dataObject->driverPath ?>'



# start web browser
browser = webdriver.Firefox(firefox_profile=profile, capabilities=firefox_capabilities, executable_path=geckoPath, firefox_options=opts)

# get source code
try:
    browser.get(pageUrl)
except WebDriverException as e:
    result['error'] = str(e)
except:
    result['error'] = "Unexpected error"




timeout = <?= $dataObject->eventTimeout ?>

#try:
#    logo_present = EC.presence_of_element_located((By.ID, 'hFull'))
#    WebDriverWait(browser, timeout).until(logo_present)
#    result['result'] = "load success"
#except TimeoutException:
#    result['result'] = "Timed out waiting for event"

#browser.save_screenshot('screenie'+ iteration + '.png')

html = browser.page_source
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

