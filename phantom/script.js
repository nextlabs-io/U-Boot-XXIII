"use strict";
var page = require('webpage').create();
var fs = require('fs');
page.onResourceReceived = function (res) {
    console.log('<!--startJson-->' + JSON.stringify(res, undefined, 4) + '<!--endJson-->');
};
page.onLoadFinished = function () {
    console.log("page load finished");
    fs.write('/var/www/parser/output.html', page.content, 'w');
    phantom.exit();
};
page.settings.userAgent = 'WhatsApp/2.20.199.14 A';
page.settings.resourceTimeout = 60000;
page.open('https://www.amazon.ca', function (status) {
    if (status !== 'success') {
        console.log('Unable to access network');
    } else {
        var ua = page.evaluate(function () {
            //return 'evaluate';
        });
        console.log(ua);
    }
    phantom.exit();
});