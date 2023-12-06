<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 27.07.18
 * Time: 23:30
 */
//$headers = getallheaders();
if (is_dir('/var/www/amazon-parser.web-experiment.info/html/data/avito')) {
    echo 'dir';
}
if (isset($_REQUEST['pi'])) {
    phpinfo();
} elseif (isset($_REQUEST['http'])) {
    $data = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') !== false) {
            $data[str_replace('HTTP_', '', $key)] = $value;
        }
    }
    print_r($data);
    echo "<div id=\"app\">app</div>";

} else {
    ?>
    <html>
    <body>
    <?php
    unset($_SERVER['HOME'], $_SERVER['USER']);
    print_r($_SERVER);
    file_put_contents(__DIR__ . '/' . $_SERVER['HTTP_X_FORWARDED_FOR'] . time() . '.log', print_r($_SERVER, 1));
    //    print_r($headers);
    ?>
    </body>
    </html>
<?php } ?>


