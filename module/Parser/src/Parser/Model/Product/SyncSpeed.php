<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 07.01.2021
 * Time: 19:41
 */

namespace Parser\Model\Product;

// simple class to calculate sync speed,
class SyncSpeed
{
    public static function calculate($newData, $oldData, $changed, $settings)
    {
        $speedLimit = $settings['syncSpeedDelayLimit'] ?? 8;
        $outOfStockSpeedLimit = $settings['syncSpeedDelayLimitOutOfStock'] ?? 20;
        $newStock = $newData['stock'];
        $speedLimit = $newStock ? $speedLimit : $outOfStockSpeedLimit;

        $currentSpeed = $oldData['sync_speed'] ?: 1;
        if ($changed && $currentSpeed > 1) {
            $currentSpeed = (int)($currentSpeed / 2);
            $currentSpeed = $currentSpeed ?: 1;
        } elseif (!$changed && $currentSpeed < $speedLimit) {
            $currentSpeed++;
        }
        return $currentSpeed;
    }
}