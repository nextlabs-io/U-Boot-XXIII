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

# import tracemalloc

# tracemalloc.start()
start_time = time.time()

pageUrl = "https://www.cdiscount.com/electromenager/aspirateurs-nettoyeurs/aspirateurs-balais/l-1101410.html#_his_"
pageUrl = "https://www.cdiscount.com/informatique/memoire-ram/corsair-memoire-pc-ddr4-vengeance-lpx-16-go-2-x/f-10716-cmk16gx4m2a2666c.html"
pageUrl = "https://amazon-parser.web-experiment.info/test.php"
iteration = '5'

# tor proxy
proxy = 'socks5://127.0.0.1:9060'
# fail proxy
# proxy = '135.181.36.161:8888'
# working proxy
# proxy = '23.146.144.156:3128'

proxyArg = '--proxy-server=%s' % proxy
ua = UserAgent()
userAgent = ua.random
result = {
    "userAgent": userAgent,
    "proxy": proxyArg
}
# print userAgent
# print proxyArg

# chrome_options
chrome_options = Options()
chrome_options.add_argument(proxyArg)
#chrome_options.add_argument('--user-agent=%s' % userAgent)
chrome_options.add_argument('--no-sandbox')
chrome_options.add_argument('--headless')
#chrome_options.add_argument('--disable-dev-shm-usage')
#chrome_options.add_argument("--use-fake-ui-for-media-stream")
#chrome_options.add_argument("--disable-user-media-security=true")
#chrome_options.add_argument('--disable-gpu')
chrome_options.add_argument('--window-size=1920x1696')
#chrome_options.add_argument('--user-data-dir=/tmp')
#chrome_options.add_argument('--hide-scrollbars')
#chrome_options.add_argument('--enable-logging')
#chrome_options.add_argument('--log-level=0')
#chrome_options.add_argument('--v=99')
chrome_options.add_argument('--single-process')
#chrome_options.add_argument('--data-path=/tmp')
chrome_options.add_argument('--ignore-certificate-errors')
#chrome_options.add_argument('--homedir=/tmp')
#chrome_options.add_argument('--disk-cache-dir=/tmp/cache-dir')
chrome_options.add_argument('--disable-blink-features=AutomationControlled')



chrome_options.add_argument("start-maximized")
chrome_options.add_experimental_option('prefs',{'profile.default_content_setting_values.notifications':1})

#chrome_options.add_experimental_option("excludeSwitches", ["enable-automation"])
#chrome_options.add_experimental_option('useAutomationExtension', False)




# start web browser
browser = webdriver.Chrome('/var/www/parser/html/phantom/chromedriver', chrome_options=chrome_options)

#browser.execute_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})")
#browser.execute_script("Object.defineProperty(window, 'chrome', {get: () => true})")
#browser.execute_script("const newProto = navigator.__proto__;delete newProto.webdriver;navigator.__proto__ = newProto;")

browser.execute_cdp_cmd('Network.setUserAgentOverride', {"userAgent": 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.53 Safari/537.36'})

result['userAgent'] = browser.execute_script("return navigator.userAgent;")

# get source code
try:
    browser.get(pageUrl)
except WebDriverException as e:
    result['error'] = str(e)
except:
    result['error'] = "Unexpected error:", sys.exc_info()[0]

timeout = 20

#await browser.evaluateOnNewDocument(() => {
#  window.navigator = {}
#})

try:
    browser.save_screenshot('screenie-headless.png')
    html = browser.page_source
    result["content_length"] = len(html)
    file = open('headless.html', 'w')
    body = browser.execute_script("return document.body.innerHTML;")


    body = body.replace("</p>", "\n")
    body = body.replace("<p>", "")
    print(body)
    #result['body'] = body
    file.write(html)
    file.close()
except:
    result['result'] = "failed to save html"
    result['error'] = "Unexpected error:", sys.exc_info()[0]

# close web browser
browser.close()
browser.quit()

result["execution_time"] = (time.time() - start_time)

pretty_data = json.dumps(result, indent=4)
print(pretty_data)
