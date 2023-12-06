<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 26.10.2020
 * Time: 17:02
 */

namespace Parser\Model\Telegram;


use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Telegram\DB\Initialize;
use Monolog\Logger;

class TelegramBot extends DefaultTablePage
{
    /**
     * @var TelegramBot
     */
    private $apiKey;
    private $botUserName;
    /**
     * @var Telegram
     */
    private $telegram;
    private $tablePrefix;

    /**
     * TelegramBot constructor.
     * @param Config $globalConfig
     * @param $botName
     * @param $apiKey
     * @throws \Exception
     */
    public function __construct(Config $globalConfig, $botName, $apiKey)
    {
        $table = 'telegram_bot';
        $tableKey = 'telegram_bot_id';

        // TODO change prefix generator to exclude problems with mysql table namings
        $this->tablePrefix = $tablePrefix = Helper::getMysqlPrefixFromString($botName) . '_';

        parent::__construct('', $globalConfig, $table, $tableKey);
        array_push($this->fields, ...['bot_name', 'bot_key']);

        $this->apiKey = $apiKey;
        $this->botUserName = $botName;
        try {
            // first check if database is initialized
            if (!Initialize::check($this->globalConfig->getDb(), $tablePrefix . 'user')) {
                Initialize::installDb($this->globalConfig->getDb(), $this->tablePrefix);
            }
            $dbCred = self::extractDbCredentials($this->globalConfig->storeConfig, 'telegram_db');
            $telegram = new Telegram($this->apiKey, $this->botUserName);
            $telegram->enableMySql($dbCred, $tablePrefix);

            $telegram->enableAdmins(['711747870']);
            // Add commands paths containing your custom commands
            $telegram->addCommandsPaths([__DIR__.'/Commands']);

             $logPaths  = [
                 'debug'  => __DIR__.'/../../../../../../data/telegram_bot/php-telegram-bot-debug.log',
                 'error'  => __DIR__.'/../../../../../../data/telegram_bot/php-telegram-bot-error.log',
                 'update' => __DIR__.'/../../../../../../data/telegram_bot/php-telegram-bot-update.log',
             ];

            // Logging (Error, Debug and Raw Updates)
            // https://github.com/php-telegram-bot/core/blob/master/doc/01-utils.md#logging
            //
            // (this example requires Monolog: composer require monolog/monolog)
             \Longman\TelegramBot\TelegramLog::initialize(
                new Logger('telegram_bot', [
                    (new \Monolog\Handler\StreamHandler($logPaths['debug'], Logger::DEBUG))->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true)),
                    (new \Monolog\Handler\StreamHandler($logPaths['error'], Logger::ERROR))->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true)),
                ]),
                new Logger('telegram_bot_updates', [
                    (new \Monolog\Handler\StreamHandler($logPaths['update'], Logger::INFO))->setFormatter(new \Monolog\Formatter\LineFormatter('%message%' . PHP_EOL)),
                ])
             );

            // Set custom Download and Upload paths
             $telegram->setDownloadPath(__DIR__.'/../../../../../../data/telegram_bot/download');
             $telegram->setUploadPath(__DIR__.'/../../../../../../data/telegram_bot/upload');

            // Load all command-specific configurations
            // foreach ($config['commands']['configs'] as $command_name => $command_config) {
            //     $telegram->setCommandConfig($command_name, $command_config);
            // }
            $telegram->setCommandConfig('avito', ['list' => 'avito']);
            $telegram->setCommandConfig('callbackquery', ['list' => 'avito']);
            // Requests Limiter (tries to prevent reaching Telegram API limits)
            $telegram->enableLimiter(['enabled' => true]);



            // if no webhook set, manual get updates
//            $telegram->handleGetUpdates();
            Request::initialize($telegram);
            $this->telegram = $telegram;
        } catch (TelegramException $e) {
            throw new \RuntimeException('failed to initialize' . $botName);
        }

    }

    public static function extractDbCredentials($config, $configSection = 'telegram_db'): array
    {
        /**
         * have
         * Array
         * (
         * [driver] => Pdo
         * [dsn] => mysql:dbname=chaos;host=192.168.1.101;
         * [username] => chaos
         * [password] => 8T!62883874GGZtc@k
         * )
         * need
         * $mysql_credentials = [
         * 'host'     => 'localhost',
         * 'user'     => 'dbuser',
         * 'password' => 'dbpass',
         * 'database' => 'dbname',
         * ];
         */
        $cred = ['host' => 'botHost', 'user' => 'botUsername', 'password' => 'botPassword', 'database' => 'botDbname'];
        $db = $config[$configSection];
        foreach ($cred as $key => $item) {
            if(!isset($db[$item])){
                throw new \RuntimeException('no config option for '. $item);
            }
            $cred[$key] = $db[$item];
        }
        return $cred;
    }

    public function setWebHook($url){
        try {
            // Create Telegram API object
            // Set webhook
            $result = $this->telegram->setWebhook($url);
            if ($result->isOk()) {
                return $result->getDescription();
            } else {
                return $result->getErrorCode();
            }
        } catch (TelegramException $e) {
            // log telegram errors
            return $e->getMessage();
        }
    }

    public function handleHook(){
        try {
            $this->telegram->handle();
        } catch (TelegramException $e) {
            // Silence is golden!
            // log telegram errors
            file_put_contents('data/log/'.$this->botUserName.'_hook.log', $e->getMessage());
            // echo $e->getMessage();
        } catch (\RuntimeException $e){
            file_put_contents('data/log/'.$this->botUserName.'_hook.log', $e->getMessage());
        }
    }

    /**
     * @param string $msg
     * @param int $chatId
     * @param null $parseMode
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws TelegramException
     */
    public function sendMessage($msg, $chatId, $parseMode = null): \Longman\TelegramBot\Entities\ServerResponse
    {
        $send = [
            'chat_id' => $chatId,
            'text'    => $msg,
        ];
        if($parseMode){
            $send['parse_mode'] = 'html';
        }
        return Request::sendMessage($send);
    }
}