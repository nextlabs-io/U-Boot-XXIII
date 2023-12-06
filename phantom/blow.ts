'use strict';
// We'll use Puppeteer is our browser automation framework.
const puppeteer = require('puppeteer-extra');
const iPhone = puppeteer.pptr.devices['iPhone 6'];

const fs = require('fs');
// add stealth plugin and use defaults (all evasion techniques)
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

// This is where we'll put the code to get around the tests.
const preparePageForTests = (async (page) => {
    const userAgent = 'Mozilla/5.0 (X11; Linux x86_64)' +
        'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.39 Safari/537.36';
    await page.setUserAgent(userAgent);
    // Pass the Webdriver Test.
    // await page.evaluateOnNewDocument(() => {
    //     Object.defineProperty(navigator, 'webdriver', {get: () => undefined});
    // });

    // Pass the Plugins Length Test.
    await page.evaluateOnNewDocument(() => {
        // Overwrite the `plugins` property to use a custom getter.
        Object.defineProperty(navigator, 'plugins', {
            // This just needs to have `length > 0` for the current test,
            // but we could mock the plugins too if necessary.
            get: () => [1, 2, 3, 4, 5]
        });
    });


    // Pass the Languages Test.
    await page.evaluateOnNewDocument(() => {
        // Overwrite the `languages` property to use a custom getter.
        Object.defineProperty(navigator, 'languages', {
            get: () => ['en-US', 'en']
        });
    });

    // Pass the iframe Test
    await page.evaluateOnNewDocument(() => {
        Object.defineProperty(HTMLIFrameElement.prototype, 'contentWindow', {
            get: function () {
                return window;
            }
        });
    });

    // Pass toString test, though it breaks console.debug() from working
    await page.evaluateOnNewDocument(() => {
        window.console.debug = () => {
            return null;
        };
    });
});


(async () => {
    // Launch the browser in headless mode and set up a page.
    // const proxy = 'socks5://91.206.14.130;9999';
// const proxy = 'http://89.208.35.81:3128';
// const proxy = 'http://195.201.129.206:3128';
// const proxy = 'http://77.236.248.237:8080'; // works
// const proxy = 'http://88.82.95.146:3128';
//const proxy = 'http://81.200.82.240:8080';
//     const proxy = 'http://svetlana.ltespace.com:15192';
    const proxy = 'http://93.157.248.112:15192';


    const browser = await puppeteer.launch({
        args: [
            '--no-sandbox',
            '--proxy-server=' + proxy
        ],
        headless: true,
    });

    const page = await browser.newPage();
    // await page.authenticate({
    //     username: 'webandpeople_gmail_com',
    //     password: 'CdWbR0qq',
    // });
    await page.setDefaultNavigationTimeout(60000);
    // await page.emulate(iPhone);
    // await page.setUserAgent('Mozilla/5.0 (Linux; Android 7.0; SM-G930V Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.125 Mobile Safari/537.36');
    await page.setExtraHTTPHeaders({
        'Accept-Language': 'ru,ru-RU'
    });
    await page.setRequestInterception(true);
    page.on('request', (request) => {
        request.continue();
        // if (request.resourceType() === 'image') request.abort();
        // else request.continue();
    });

    const navigationPromise = page.waitForNavigation({waitUntil: "domcontentloaded"});
    await page.setDefaultNavigationTimeout(80000);
    // Prepare for the tests (not yet implemented).
    // await preparePageForTests(page);

    // const testUrl = 'https://amazon-parser.web-experiment.info/blow.php?http=1';
    const testUrl = 'https://bot.sannysoft.com/';
    try {
        await page.goto(testUrl);
        await navigationPromise;
        const content = await page.content();
        if (content.length > 200) {
            console.log(content.length);
            await fs.writeFile('ts-blow.html', content, function (error) {
                if (error) throw error; // если возникла ошибка
                console.log("Асинхронная запись файла завершена.");

            });
            console.log(content);
        } else {
            console.log('failed request');
            // console.log(await page.content());
            await fs.writeFile('ts-blow-error.html', content, function (error) {
                if (error) throw error; // если возникла ошибка
                console.log("запись файла завершена.");
            });
        }
        // Save a screenshot of the results.
        await page.screenshot({path: 'headless-test-blow-result.png'});

    } catch (e) {
        console.log(e.message);
        await page.screenshot({path: 'headless-test-blow-failure.png'});
    }
    await browser.close()
})();
