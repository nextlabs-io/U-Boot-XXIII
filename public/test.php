<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 08.12.2020
 * Time: 12:52
 */
?>
<html>
<head>
    <script type="text/javascript">

        ready(function () {
            if(navigator.webdriver) {
                writeContent('navigator.webdriver Chrome headless detected');
            }
            if(!window.chrome) {
                writeContent("isChrome && !window.chrome Chrome headless detected");
            }

            navigator.permissions.query({name:'notifications'}).then(function(permissionStatus) {
                if(Notification.permission === 'denied' && permissionStatus.state === 'prompt') {
                    writeContent('permissions.query This is Chrome headless')
                }
            });

            if(navigator.plugins.length === 0) {
                writeContent("plugins.length It may be Chrome headless");
            }

            if(navigator.languages === "") {
                writeContent("navigator.languages Chrome headless detected");
            }

        });
        function ready(callback){
            // in case the document is already rendered
            if (document.readyState!='loading') callback();
            // modern browsers
            else if (document.addEventListener) document.addEventListener('DOMContentLoaded', callback);
            // IE <= 8
            else document.attachEvent('onreadystatechange', function(){
                    if (document.readyState=='complete') callback();
                });
        }

        function writeContent(text){
            var body = document.getElementById('body-id');
            var node = document.createElement("P");                 // Create a <li> node
            var textnode = document.createTextNode(text);         // Create a text node
            node.appendChild(textnode);                              // Append the text to <li>
            body.appendChild(node);     // Append <li> to <ul> with id="myList"
        }
    </script>

</head><body id="body-id">===========</body></html>
