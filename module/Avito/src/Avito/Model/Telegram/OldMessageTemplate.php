<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.11.2020
 * Time: 15:00
 */
namespace Avito\Model\Telegram;


class OldMessageTemplate
{
    public static function getMessage($data){
        /**
         * <b>Изменение объявления</b>
        Продам 4 новые шины 215/55R17 Hankook W429
        <b style="color:red">цена 11500 руб.</b>
        https://avito.ru/kemerovo/zapchasti_i_aksessuary/prodam_novye_shiny_21555r17_hankook_w429_2005848421
        в категории
        https://www.avito.ru/novosibirsk/zapchasti_i_aksessuary/shiny_diski_i_kolesa/shiny/diametr_17/zimnie_shipovannye-ASgBAgICBEQKJooLgJ0BugvwoQG8C5CiAQ?cd=1&f=ASgBAgICBUQKJooLgJ0BugvwoQG8C5CiAdC3DdKFMw&q=hankook
         */
        /**
         * ['item_id' => $itemId, 'title' => $title, 'price' => $price, 'link' => $url, 'offer' => $offer];
         */
        $message = [];
        $message[] = '<b>Изменение объявления</b>';
        $message[] = $data['title'];
        if($data['price']){
            $message[] = '<b>Цена '. $data['price'] . ' руб.</b>';

        } else {
            $message[] = '<b>Цена не указана</b>';
        }
        if($data['oldPrice']){
            $message[] = '<b>Старая цена '. $data['oldPrice'] . ' руб.</b>';
        }
        $message[] = $data['link'];

        return implode("\r\n", $message);
    }
}