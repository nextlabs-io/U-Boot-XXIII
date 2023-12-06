<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 14.07.2019
 * Time: 14:27
 */

namespace Parser\Model\Amazon\Centric;

class ProductApiResponse {
    // added to centric campaing and will be processed in the future
    public const SuccessToAddToCampaign = 20;
    // by some reason centric could not use it
    public const FailToAddToCampaign = 21;
    // processed and data found
    public const FoundOnCentric = 22;
    // processed but data not found
    public const NotFoundOnCentric = 23;

}