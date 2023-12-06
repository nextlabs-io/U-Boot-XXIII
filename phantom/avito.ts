'use strict';
// We'll use Puppeteer is our browser automation framework.
const puppeteer = require('puppeteer-extra');
const iPhone = puppeteer.pptr.devices['iPhone 6'];

const fs = require('fs');
// add stealth plugin and use defaults (all evasion techniques)
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
const RecaptchaPlugin = require('puppeteer-extra-plugin-recaptcha')
puppeteer.use(StealthPlugin());
// puppeteer.use(
//     RecaptchaPlugin({
//         provider: {
//             id: '2captcha',
//             token: 'XXXXXXX', // REPLACE THIS WITH YOUR OWN 2CAPTCHA API KEY ⚡
//         },
//         visualFeedback: true, // colorize reCAPTCHAs (violet = detected, green = solved)
//     })
// );

var args = process.argv.slice(2);
// console.log('args', args);

if (args[0] !== undefined) {
    // console.log('file', args[0]);
    var pageData = JSON.parse(fs.readFileSync(args[0], "utf8"));
    // var pageData = require(args[0]);
} else {
    var pageData = null;
}
// console.log('pageData', pageData);

if (!pageData) {
    console.log('no page data');
    throw new Error('pageData empty');
}
(async () => {
    if (pageData.proxyType == 'socks5') {
        var proxyType = 'socks5://';
    } else {
        var proxyType = '';
    }

    const proxy = proxyType + pageData.proxyHost + ':' + pageData.proxyPort;
    // const proxy = "45.63.85.96:3128";
    console.log('using proxy ' + proxy);
    const browser = await puppeteer.launch({
        userDataDir: pageData.userDataDir,
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
    // await page.emulate(iPhone);
    if (pageData.userAgent) {
        await page.setUserAgent(pageData.userAgent);
    }
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
    if (pageData.device) {
        await page.emulate(iPhone);
    }
    // Prepare for the tests (not yet implemented).
    // await preparePageForTests(page);

    console.log(Date.now());
    function sleep(ms) {
        return new Promise((resolve) => {
            setTimeout(resolve, ms);
        });
    }
    try {
        for (let i = 0; i < pageData.urls.length; i++) {
            console.log('processing url ' + pageData.urls[i].url);
            await page.goto(pageData.urls[i].url);
            await navigationPromise;
            // await page.waitForSelector("#app");
            await page.$('iframe');

            const elementHandle = await page.$('div.g-recaptcha iframe');
            console.log('got frame handle');
            const frame = await elementHandle.contentFrame();

            const element = await frame.$("#recaptcha-anchor");
            console.log('got checkbox selector');
            // if (element) {
            //     console.log('clicking element');
            //     await element.click();
            // }
            await sleep(3127);
            const clickableElem = await page.$('div.g-recaptcha');
            if (clickableElem) {
                console.log('clicking element');
                const check = clickableElem.click();
                await clickableElem.click();
            }
            await sleep(5000);

            await frame.$('#recaptcha-accessible-status');

//            await page.waitForSelector("#global-search-catid");

            let content = await page.content();

            await page.screenshot({path: 'walmart.png'});

            pageData.urls[i].data = content;
            await fs.writeFile('walmart.html', content, function (error) {
                if (error) throw error; // если возникла ошибка
                console.log("запись файла завершена. walmart.html ");
            });
        }
        const dataToSave = JSON.stringify(pageData);
        await fs.writeFile(pageData.contentFilePath, dataToSave, function (error) {
            if (error) throw error; // если возникла ошибка
            console.log("запись файла завершена. " + pageData.contentFilePath);
        });
        // await page.screenshot({path: 'headless-test-result.png'});
    } catch (e) {
        console.log(e.message);
        await page.screenshot({path: 'headless-test-failure.png'});
        const dataToSave = JSON.stringify(pageData);
        await fs.writeFile(pageData.contentFilePath, dataToSave, function (error) {
            if (error) throw error; // если возникла ошибка
            console.log("запись файла завершена. " + pageData.contentFilePath);
        });
    }

    // Clean up.
    await browser.close()
})();
