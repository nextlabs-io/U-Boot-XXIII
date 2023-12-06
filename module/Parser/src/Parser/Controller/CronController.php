<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.07.2019
 * Time: 19:32
 */

namespace Parser\Controller;


use Parser\Model\Helper\Config;
use Parser\Model\Amazon\Centric\CentricAPI;
use Parser\Model\Helper\ProcessLimiter;
use Laminas\View\Model\ViewModel;

/**
 * Class CronController
 * @package Parser\Controller
 * TODO move all cron commands to this controller.
 */
class CronController extends AbstractController
{
    public $db;
    public $config;

    /**
     * MagentoController constructor.
     * @param Config $config
     */

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = $config->getDb();
        $this->authActions = [];
    }

    public function centricAction(): ViewModel
    {
        ini_set('ignore_user_abort', 1);
        // manual sync start in development stage
        $enabled = $apiKey = $this->config->getConfig('centric')['enabled'] ?? null;

        if (!$enabled) {
            pr('disabled by config');
            die();
        }
        $campaignId = $this->params()->fromQuery('campaign');
        $locale = $this->params()->fromQuery('locale', 'ca');
        // delay a sync run if multiple cron commands are running every minute.
        $delay = $this->params()->fromQuery('delay', '');
        if ($delay) {
            sleep($delay * 3);
        }
        // getting api key from config

        $apiKey = $this->config->getConfig('centric')['centricApiKey'] ?? null;
        if (!$apiKey) {
            throw new \Exception('no api key');
        }

        if (!$campaignId) {
            /* Array(    [ca] => 15389,15390 )           */
            $localeCampaignString = $this->config->getConfig('centric')['centricCampaigns'] ?? [];
            if (is_array($localeCampaignString) && count($localeCampaignString)) {
                foreach ($localeCampaignString as $locale => $item) {
                    if ($item) {
                        $campaignList[$locale] = explode(',', $item);
                    }
                }
            }
        } else {
            $campaignList[$locale][] = $campaignId;
        }

        $limiter = new ProcessLimiter($this->config, [
            'path' => 'centric',
            'expireTime' => 256,
            'processLimit' => 1,
        ]);
        if ($limiterID = $limiter->initializeProcess()) {
            foreach ($campaignList as $locale => $campaigns) {
                foreach ($campaigns as $campaignId) {
                    pr('starting operations for campaign:' . $campaignId);
                    $centric = new CentricAPI($this->db, $this->config, $apiKey);
                    try {
                        $centric->processCampaigns($campaignId, $locale);
                    } catch (\Exception $e) {
                        pr('error for ' . $campaignId);
                        pr($e->getMessage());
                    }
                }
            }
            $limiter->delete(['process_limiter_id' => $limiterID]);
        } else {
            pr('process is already running');
        }

        // once we have a 100% campaign, we get the products and update them into the database
        /* 2. adding products here  from a completed campaign*/
        //$centric->getProducts();

        /* 3. empty completed campaigns */

        /* 4. check for new products to add to campaigns*/

        return $this->returnZeroTemplate();
    }
}