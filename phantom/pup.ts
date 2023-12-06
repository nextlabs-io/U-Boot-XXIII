'use strict';
// We'll use Puppeteer is our browser automation framework.
const puppeteer = require('puppeteer-extra');
const iPhone = puppeteer.pptr.devices['iPhone 6'];


const fs = require('fs');
// add stealth plugin and use defaults (all evasion techniques)
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
let pageData = require('./configFile.json');


puppeteer.use(StealthPlugin());


// const testUrl = 'https://amazon-parser.web-experiment.info/blow.php?http=1';
// const firstUrl = 'https://www.cdiscount.com/?qid=' + Date.now();
// const testUrl = 'https://www.cdiscount.com/electromenager/aspirateurs-nettoyeurs/aspirateurs-balais/l-1101410.html#_his_?qid=' + Date.now();
const testUrl = 'https://www.walmart.com/ip/Hot-Wheels-Power-Shift-Raceway-Track-5-Race-Vehicles-Set/510311734?athcpid=510311734';
const firstUrl = 'https://m.avito.ru/?qid=' + Date.now();
// const testUrl = 'https://m.avito.ru/novosibirsk?q=narada&qid=' + Date.now();

// let pageData = [
//     { Field: '0', Url: 'https://m.avito.ru/?qid=' + Date.now(), Data: ''},
//     { Field: '1', Url: 'https://m.avito.ru/novosibirsk?q=narada&qid=' + Date.now(), Data: ''}
// ];
let data  = '';


(async () => {
    // Launch the browser in headless mode and set up a page.
// const proxy = 'socks5://127.0.0.1:9060';
//     const proxy = '54.67.107.171:3128';

    const proxy = '104.128.237.22:3128';
    // const proxy = '144.91.70.15:3128';

    const browser = await puppeteer.launch({
        userDataDir: '/var/www/parser/html/phantom/puppeteer/',
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
    // await page.setUserAgent('Mozilla/5.0 (Linux; Android 7.0; SM-G930V Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.125 Mobile Safari/537.36');
    await page.setExtraHTTPHeaders({
        'Accept-Language': 'ru,ru-RU'
    });
    // await page.setRequestInterception(true);
    // page.on('request', (request) => {
    //     request.continue();
    //     // if (request.resourceType() === 'image') request.abort();
    //     // else request.continue();
    // });

    const navigationPromise = page.waitForNavigation({waitUntil: "domcontentloaded"});
    await page.setDefaultNavigationTimeout(30000);
    await page.emulate(iPhone);
    // Prepare for the tests (not yet implemented).
    // await preparePageForTests(page);

    console.log(Date.now());
    console.log(pageData);
    try {
        for(let i = 0; i < pageData.length; i++)
        {
            console.log('processing url ' + pageData[i].Url);
            await page.goto(pageData[i].Url);
            await navigationPromise;
            await page.waitForSelector("#app");
            let content = await page.content();
            // const element = await page.$("#body-id");
            pageData[i].Data = content;
        }
        const dataToSave = JSON.stringify(pageData);
        await fs.writeFile('pup.json', dataToSave, function (error) {
            if (error) throw error; // если возникла ошибка
            console.log("запись файла завершена. pup.json");
        });
        // await page.goto(firstUrl);
        // // const element = await page.$("#body-id");
        // await navigationPromise;
        // // const navigationPromise1 = page.waitForNavigation({waitUntil: "domcontentloaded"});
        // // const element = await page.waitForSelector("#hFull");
        // const content = await page.content();
        // if (content.length > 1000) {
        //     await page.goto(testUrl);
        //     await navigationPromise;
        //     const content = await page.content();
        //     if (content.length > 1000) {
        //         await page.waitForSelector("div[data-marker$=\"search-form\"]");
        //         console.log(content.length);
        //         await fs.writeFile('pup.html', content, function (error) {
        //             if (error) throw error; // если возникла ошибка
        //             console.log("Асинхронная запись файла завершена.");
        //         });
        //     } else {
        //         console.log('failed request');
        //         // console.log(await page.content());
        //         await fs.writeFile('pup-error.html', content, function (error) {
        //             if (error) throw error; // если возникла ошибка
        //             console.log("запись файла завершена.");
        //         });
        //     }
        //
        // } else {
        //     console.log('failed request');
        //     // console.log(await page.content());
        //     await fs.writeFile('pup-error.html', content, function (error) {
        //         if (error) throw error; // если возникла ошибка
        //         console.log("запись файла завершена.");
        //     });
        //
        // }
        //
        //
        // Save a screenshot of the results.
        await page.screenshot({path: 'headless-test-result.png'});

    } catch (e) {
        console.log(e.message);
        await page.screenshot({path: 'headless-test-failure.png'});
        const dataToSave = JSON.stringify(pageData);
        await fs.writeFile('pup-error.json', dataToSave, function (error) {
            if (error) throw error; // если возникла ошибка
            console.log("запись файла завершена. pup-error.json");
        });
    }

    // Clean up.
    await browser.close()
})();
