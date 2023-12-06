"use strict";
var page;
//var myurl="https://www.cdiscount.com/juniors/plein-air/pat-patrouille-voiture-electrique-camion-de-police/f-12004420401-bik3700965112042.html?idOffre=400184969#cm_sp=PA:4490131:4:BIK3700965112042";
var myurl="https://www.walmart.com/";
var myurl="https://www.walmart.com/ip/Hot-Wheels-Power-Shift-Raceway-Track-5-Race-Vehicles-Set/510311734?athcpid=510311734&athpgid=athenaHomepage&athcgid=dealspage-home-61381&athznid=ItemCarouselType_BestInDeals&athieid=v1&athstid=CS020&athguid=466001f5-9a18a716-81eb25c89f15260d&athancid=null&athena=true";
var fs = require('fs');
var renderPage = function (url) {
    page = require('webpage').create();

    page.onLoadFinished = function () {
        console.log("page load finished");
        fs.write('/var/www/parser/output.html', page.content, 'w');
    };
    page.onNavigationRequested = function(url, type, willNavigate, main) {
        if (0 && main && url!=myurl && url.replace(/\/$/,"")!=myurl && url != 'about:blank' ) {
            // main = navigation in main frame; type = not by click/submit etc
            console.log("\tfollowing "+myurl+" redirect to "+url)
            myurl = url;
            console.log("redirect caught");
            page.close();
            renderPage(url);
        }
    };
    page.settings.userAgent = 'WhatsApp/2.20.199.14 A';
    page.settings.resourceTimeout = 60000;
    page.open(url, function(status) {
        if (status==="success") {
            console.log("success");
            page.render('/var/www/parser/output.png');
            phantom.exit(0);
//            setTimeout(phantom.exit(0), 10000);
        } else {
            console.log("failed");
            phantom.exit(1);
        }
    });
}

renderPage(myurl);