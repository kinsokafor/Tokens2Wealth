<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use DateTimeZone;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Api\Config;

final class Loan extends Accounts
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function loanComponents($id) {
        $data = self::getOnServer($id);
        $response = array();
        $response['nextSettlementDate'] = ThriftSavings::nextSettlementDate();
        $response['monthsSpent'] = self::monthsSpent($data, $response['nextSettlementDate']);
        $response['remainingMonths'] = $data->tenure - $response['monthsSpent'];
        $response['moratorium'] = isset($data->moratorium) ? (int) $data->moratorium : 0;
        $response['balance'] = self::getBalance($data->ac_number);
        if($response['remainingMonths'] > 1) {
            $response['debitAmount'] = ($response['balance'] * -1)/$response['remainingMonths'];
        } else $response['debitAmount'] = ($response['balance'] * -1);
        $response['interest'] = self::getInterest($data);
        $response['sumRepaid'] = ($data->amount + $response['interest']) + $response['balance'];
        $response['interestRepaid'] = $data->rate * 0.01 * $response['sumRepaid'];
        $response['principalRepaid'] = $response['sumRepaid'] - $response['interestRepaid'];
        return $response;
    }

    public static function monthsSpent($data, $nextSettlement) {
        $config = new Config();
        $rtsd = Options::get('rt_settlement');
        $dateTo = date_create($nextSettlement, new DateTimeZone($config->timezone));
        $now = date_create('now', new DateTimeZone($config->timezone));
        $moratorium = isset($data->moratorium) ? $data->moratorium : 0;
        if($now->getTimestamp() > $dateTo->getTimestamp()) {
            // This month settlement is still ahead of time
            // add one month to get next month settlement
            $interval = new \DateInterval('P1M');
            $dateTo->add($interval);
        }
        $dateFrom = date_create($data->time_altered, new DateTimeZone($config->timezone));
        $from_month = date_create($dateFrom->format("Y-m-$rtsd 00:00:00"), new DateTimeZone($config->timezone));
        if($dateFrom->getTimestamp() < $from_month->getTimestamp()) { // before settlement date
            // This month settlement is still ahead of time
            // adds moratorium month to get first settlement month
            if($moratorium > 0) {
                $interval = new \DateInterval('P'.$moratorium.'M');
                $dateFrom->add($interval);
            }
        } else {
            $interval = new \DateInterval('P1M');
            $dateFrom->add($interval);
            if($moratorium > 1) {
                $moratorium = $moratorium - 1;
                $interval = new \DateInterval('P'.$moratorium.'M');
                $dateFrom->add($interval);
            }
        }
        return ($dateFrom->diff($dateTo)->invert === 0) ? ($dateFrom->diff($dateTo)->y * 12) + $dateFrom->diff($dateTo)->m : 0;
    }

    public static function getInterest($data) {
        $p = is_numeric((float) $data->amount) ? (float) $data->amount : 1;
        $t = is_numeric((float) $data->tenure) ? (float) $data->tenure/12 : 1;
        $r = is_numeric((float) $data->rate) ? (float) $data->rate : 1;
    
        return ($p*$t*$r)/100;
    }
}

?>