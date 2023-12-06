<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 04.06.2020
 * Time: 16:45
 */

namespace Parser\Model\Amazon\Attributes;


use Parser\Model\SimpleObject;


class FastTrack extends SimpleObject
{
    protected static $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    protected static $months2 = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    public $status;
    public $days;

    public function getDate($fastTrackString, $countries = []): string
    {
        /**
         * 5200    Want it tomorrow, June 4? Order it in the next  and choose One-Day Delivery at checkout.
         * 4484    Want it Monday, June 8? Order it in the next  and choose Standard Shipping at checkout.
         * 480    FREE delivery
         * 471    NULL
         * 419    Get it as soon as June 18 - July 2 when you choose Standard Shipping at checkout.
         * 381    This item ships to Germany. Want it Wednesday, June 24? Choose AmazonGlobal Priority Shipping at checkout. Learn more
         * 381    Get it as soon as June 22 - July 9 when you choose Standard Shipping at checkout.
         * Arrives: Tuesday, June 9 Details Fastest delivery: Monday, June 8 Order within 8 hrs and 40 mins
         * Arrives:  Aug 28 - Sep 21 Fastest delivery: Aug 13 - 18
         */
        // locale dependent

        $countryMarker = ['ships to'];
        $dateMarker = ['Want it', 'Get it', 'Arrives'];
        $dateFormat = 'M d';
        $string = '';
        $month = '';
        if ($fastTrackString) {
            $match = str_replace(self::$months, '', $fastTrackString) !== $fastTrackString;
            $match2 = str_replace(self::$months2, '', $fastTrackString) !== $fastTrackString;
            $fastTrackArray = explode(' ', $fastTrackString);
            if ($match) {
                $intersect = array_intersect($fastTrackArray, self::$months);
                // found some date marker, now there are two options - dates range or just date
                return $this->getDateByMonth($fastTrackArray, $intersect);
            }

            if ($match2) {
                $intersect = array_intersect($fastTrackArray, self::$months2);
                // found some date marker, now there are two options - dates range or just date
                return $this->getDateByMonth($fastTrackArray, $intersect);

            }
            if (str_replace($countryMarker, '', $fastTrackString) !== $fastTrackString) {
                // check for the proper country
                $this->addError('country mismatch');
                return '';
            }

            if (strpos($fastTrackString, 'FREE delivery') !== false) {
                return 'no date specified';
            }
        }
        return 'no date specified';

    }

    protected function getDateByMonth($fastTrackArray, $intersect): string
    {
        if (!count($intersect)) {
            $this->days = 'undefined';
            return '';
        }
        $dates = [];
        $days = [];
        while ($month = array_shift($intersect)) {
            $key = array_search($month, $fastTrackArray, true);
            $day = $fastTrackArray[$key + 1] ?? '';
            $day = $day ? (int)$day : '';
            $dates[] = $month . ' ' . $day;
            $days[] = $this->calculateDaysToDeliver($month, $day);
            unset($fastTrackArray[$key]);
        }
        $this->days = implode(' - ', $days);
        return implode(';', $dates);
    }

    public function calculateDaysToDeliver($month, $day, $months = null): int
    {
        if (!$months) {
            $months = self::$months;
        }
        $deliveryMonth = array_search($month, $months, true) + 1;
        $currentMonth = date('n');
        // situation, current month is 12, delivery month is 1 - i.e. next year
        $deliveryYear = ($deliveryMonth < $currentMonth) ? date('Y') + 1 : date('Y');

        $now = new \DateTime(); // текущее время на сервере
        $date = \DateTime::createFromFormat('Y-n-j H:i', $deliveryYear . '-' . $deliveryMonth . '-' . $day . ' 23:59');
        return $date->diff($now)->d;
    }
}