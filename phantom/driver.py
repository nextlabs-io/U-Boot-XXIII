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

# pageUrl = "https://www.cdiscount.com/electromenager/aspirateurs-nettoyeurs/aspirateurs-balais/l-1101410.html#_his_"
pageUrl = "https://www.cdiscount.com/informatique/memoire-ram/corsair-memoire-pc-ddr4-vengeance-lpx-16-go-2-x/f-10716-cmk16gx4m2a2666c.html"
# pageUrl = "https://amazon-parser.web-experiment.info/test.php"
# pageUrl = "https://bot.sannysoft.com/"
iteration = '-cdiscount'

# tor proxy
proxy = 'socks5://127.0.0.1:9080'
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
chrome_options.add_argument('--user-agent=%s' % userAgent)
chrome_options.add_argument('--no-sandbox')
chrome_options.add_argument('--headless')
chrome_options.add_argument('--disable-dev-shm-usage')

# start web browser
browser = webdriver.Chrome('/var/www/parser/html/phantom/chromedriver', chrome_options=chrome_options)

# get source code
try:
    browser.get(pageUrl)
except WebDriverException as e:
    result['error'] = str(e)
except:
    result['error'] = "Unexpected error:", sys.exc_info()[0]

timeout = 20


try:
    browser.save_screenshot('screenie' + iteration + '.png')
    html = browser.page_source
    result["content_length"] = len(html)
    file = open('driver' + iteration + '.html', 'w')
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
