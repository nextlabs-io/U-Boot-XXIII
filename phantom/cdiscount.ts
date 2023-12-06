'use strict';
// We'll use Puppeteer is our browser automation framework.
const puppeteer = require('puppeteer-extra');
const iPhone = puppeteer.pptr.devices['iPhone 6'];

const fs = require('fs');
// add stealth plugin and use defaults (all evasion techniques)
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());


var args = process.argv.slice(2);
// console.log('args', args);

if(args[0] !== undefined){
    // console.log('file', args[0]);
    var pageData = JSON.parse(fs.readFileSync(args[0], "utf8"));
    // var pageData = require(args[0]);
} else {
    var pageData = null;
}
// console.log('pageData', pageData);

if(!pageData){
    console.log('no page data');
    throw new Error('pageData empty');
}
(async () => {
    if(pageData.proxyType == 'socks5'){
        var proxyType = 'socks5://';
    } else {
        var proxyType = '';
    }

    const proxy = proxyType + pageData.proxyHost + ':' + pageData.proxyPort;
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
    // proxy auth sample
    // await page.authenticate({
    //     username: 'username',
    //     password: 'pass',
    // });
    if(pageData.userAgent) {
        await page.setUserAgent(pageData.userAgent);
    }
    // await page.setExtraHTTPHeaders({
    //     'Accept-Language': 'ru,ru-RU'
    // });
    // await page.setRequestInterception(true);
    // page.on('request', (request) => {
    //     request.continue();
    //     // if (request.resourceType() === 'image') request.abort();
    //     // else request.continue();
    // });

    await page.setDefaultNavigationTimeout(30000);
    if(pageData.device) {
        await page.emulate(iPhone);
    }
    // Prepare for the tests (not yet implemented).
    // await preparePageForTests(page);

    console.log(Date.now());
    const navigationPromise = page.waitForNavigation({waitUntil: "domcontentloaded"});

    try {
        for(let i = 0; i < pageData.urls.length; i++)
        {
            console.log('processing url ' + pageData.urls[i].url);
            const response = await page.goto(pageData.urls[i].url);
            console.log('request cache');
            console.log(response.fromCache());
            await navigationPromise;
            await page.waitForSelector("#hFull");
            let content = await page.content();
            // const element = await page.$("#body-id");
            pageData.urls[i].data = content;
        }
        const dataToSave = JSON.stringify(pageData);
        await fs.writeFile(pageData.contentFilePath, dataToSave, function (error) {
            if (error) throw error;
            console.log("file saved");
        });
        // await page.screenshot({path: 'headless-test-result.png'});
    } catch (e) {
        console.log(e.message);
        await page.screenshot({path: 'headless-test-failure.png'});
        const dataToSave = JSON.stringify(pageData);
        await fs.writeFile(pageData.contentFilePath, dataToSave, function (error) {
            if (error) throw error;
            console.log("error file saved");
        });
    }

    // Clean up.
    await browser.close()
})();
