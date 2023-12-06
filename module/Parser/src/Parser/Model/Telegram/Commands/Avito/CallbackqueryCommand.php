<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 *
 * (c) PHP Telegram Bot Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Callback query command
 *
 * This command handles all callback queries sent via inline keyboard buttons.
 *
 * @see InlinekeyboardCommand.php
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Handle the callback query';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * Main command execution
     *
     * @return ServerResponse
     * @throws \Exception
     */
    public function execute(): ServerResponse
    {

        // Callback query data can be fetched and handled accordingly.
        $callback_query = $this->getCallbackQuery();
        $callback_data = $callback_query->getData();
//        $inline_keyboard = new InlineKeyboard([
//            ['text' => '2 callback', 'callback_data' => 'identifieravito'],
//        ]);

        $chat_id = '123';
        $messageId = '123';
        if ($message = $callback_query->getMessage() ?: $callback_query->getEditedMessage() ?: $callback_query->getChannelPost() ?: $callback_query->getEditedChannelPost()) {
            $chat_id = $message->getChat()->getId();
            $messageId = $message->getMessageId();
        }
        $this->replyToChat('Keyboard Hidden', [
            'reply_markup' => Keyboard::remove(),
        ]);

        $data = [
            'chat_id' => $chat_id,
            'text' => $chat_id,
            'reply_markup' => $keyboard,
        ];
        Request::deleteMessage(['chat_id' => $chat_id, 'message_id' => $messageId]);

        $inlineKeyboard = new InlineKeyboard([
            ['text' => 'Callback 2', 'callback_data' => '/avito'],

        ]);
        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'callback 2',
            'reply_markup' => $inlineKeyboard,
        ]);
        return $callback_query->answer([
            'text' => 'Content of the callback data received 2: ' . $callback_data,
            'show_alert' => false,
            'cache_time' => 1,
//            'reply_markup' => $keyboard,
        ]);
    }
}
