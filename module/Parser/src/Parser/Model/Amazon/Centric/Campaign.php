<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 14.07.2019
 * Time: 14:27
 */

namespace Parser\Model\Amazon\Centric;


class Campaign
{
    // empty campaign is ready to get products and process them
    public const Empty = 1;
    // full campaign and completed, ready to deliver data and got emptied
    public const FullCompleted = 2;
    // full campaign but in the process.
    public const FullInProgress = 3;
    // full but not yet started to process data.
    public const FullWaiting = 4;

    public static function getCampaignStatus($data)
    {
        /*
         [attributes] => Array
                (
                    [errorItems] => 0
                    [totalItems] => 0
                    [convertedItems] => 0
                    [percentage] => 0
                    [jobsCount] => 0
                    [listingsReturned] => 3245
                    [cancelJob] =>
                )
        */
        if ($data['totalItems'] == 0) {
            return self::Empty;
        } elseif ($data['percentage'] == 100) {
            return self::FullCompleted;
        } elseif ($data['jobsCount']) {
            return self::FullInProgress;
        }
        return self::FullWaiting;
    }
}