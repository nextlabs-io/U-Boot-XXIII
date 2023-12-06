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

namespace Longman\TelegramBot\Commands\UserCommands;

/**
 * User "/inlinekeyboard" command
 *
 * Display an inline keyboard with a few buttons.
 *
 * This command requires CallbackqueryCommand to work!
 *
 * @see CallbackqueryCommand.php
 */

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

class AvitoCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'avito';

    /**
     * @var string
     */
    protected $description = 'add Avito category to list';

    /**
     * @var string
     */
    protected $usage = '/avito';

    /**
     * @var string
     */
    protected $version = '0.2.0';

    /**
     * Main command execution
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $inline_keyboard = new InlineKeyboard([
            ['text' => 'Callback', 'callback_data' => '/avito'],
            ['text' => 'Open URL', 'url' => 'https://github.com/php-telegram-bot/example-bot'],
        ]);

//        $inline_keyboard->setOneTimeKeyboard();
        $data = print_r($this->config, true);
        return $this->replyToChat('callback 1', [
            'reply_markup' => $inline_keyboard,
        ]);
    }
}
