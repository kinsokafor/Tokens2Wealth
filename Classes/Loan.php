<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use DateTimeZone;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\User;
use EvoPhp\Api\Config;
use EvoPhp\Resources\Meta;

final class Loan extends Accounts
{
    use AdminLog;

    use Meta;
    
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
        $response['percentageCompleted'] = ($response["sumRepaid"] * 100)/(float) $data->repayment_sum;
        $response['interestRepaid'] = ($response['interest'] * $response['percentageCompleted'])/100;
        $response['principalRepaid'] = ($data->amount * $response['percentageCompleted'])/100;
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

    public static function terminateAfterCredit($cr) {
        if(substr($cr->account, 0, 2) != '321') return;
        $balance = Accounts::getBalance($cr->account);
        if($balance < 0) {
            return;
        }
        self::terminate($cr->account);
    }

    public static function terminate($account) {
        
    }

    public static function new($params) {
        extract($params);
        $self = new self;
        $session = Session::getInstance();
        $userId = $session->getResourceOwner()->user_id;
        $account = Accounts::createAccount($userId, "loan", true);
        if($account == NULL) {
            http_response_code(400);
            return "Something went wrong";
        }
        $interest = $self::getInterest((object) [
            "rate" => Options::get("loan_rate"),
            "tenure" => $tenure,
            "amount" => $amount
        ]);
        $metaSet = [
            "rate" => Options::get("loan_rate"),
            "moratorium" => Options::get("moratorium") ?? 1,
            "amount" => (float) $amount,
            "tenure" => $tenure,
            "plan" => "Against Thrift Savings",
            "repayment_sum" => ($interest + $amount),
            "gt1_id" => "NA",
            "gt1_fullname" => "NA",
            "gt2_id" => "NA",
            "gt2_fullname" => "NA"
        ];
        $user = new User;
        if(isset($gt1_id)) {
            $meta = $user->get((string) $gt1_id);
            if($meta == null) {
                http_response_code(400);
                return "$gt1_id is not a valid membership ID.";
            }
            $verified = self::verifyGuarantor($meta, $amount);
            if($verified !== true) {
                return $verified;
            }
            $metaSet["gt1_id"] = $gt1_id;
            $metaSet["gt1_fullname"] = $gt1_fullname;
            $metaSet["gt1_approval"] = "pending";
            Messages::guarantorNomination($meta->id);
        }
        if(isset($gt2_id)) {
            if($gt2_id == $gt1_id) {
                http_response_code(400);
                return "You used the same guarantor.";
            }
            $meta = $user->get((string) $gt2_id);
            if($meta == null) {
                http_response_code(400);
                return "$gt2_id is not a valid membership ID.";
            }
            $verified = self::verifyGuarantor($meta, $amount);
            if($verified !== true) {
                return $verified;
            }
            $metaSet["gt2_id"] = $gt2_id;
            $metaSet["gt2_fullname"] = $gt2_fullname;
            $metaSet["gt2_approval"] = "pending";
            Messages::guarantorNomination($meta->id);
        }
        $self->dbTable->update("t2w_accounts")
            ->set("status", "in process")
            ->metaSet($metaSet, [], json_decode($account->meta))
            ->where("id", $account->id)
            ->execute();

        return Accounts::getById($account->id);
    }

    public static function verifyGuarantor($meta, $loanAmount) {
        set_time_limit(200);
        $session = Session::getInstance();
        $userId = $session->getResourceOwner()->user_id;

        if($userId == $meta->id) {
            http_response_code(400);
            return "Sorry you cannot surety yourself. Change $meta->username to someone else membership ID.";
        }

        if($meta->role != "member") {
            http_response_code(400);
            return "Sorry the user with membership ID $meta->username cannot be used as your guarantor. Bring a fellow member/participant";
        }

        $gteWallet = self::merge(Accounts::getSingle(["user_id" => $meta->id, "ac_type" => "contribution"]));
        $eWallet = self::merge(Accounts::getSingle(["user_id" => $userId, "ac_type" => "contribution"]));

        if($gteWallet->level <= $eWallet->level) {
            http_response_code(400);
            return "Sorry the user with membership ID $meta->username cannot be used as your guarantor. Your guarantor must be someone higher than you in level";
        }

        if(!self::testGuarantorCapacity($meta, $loanAmount)) {
            http_response_code(400);
            return "Sorry the user with membership ID $meta->username did not pass the system check test and cannot be used as your guarantor.";
        }

        return true;
    }
    
    public static function testGuarantorCapacity(object $gt_meta, float $loanAmount = 0) {

        $liability = self::guarantorLiability($gt_meta->username);
        if($loanAmount <= 0) return false;

        // guarantor's regular thrift
        $thrift = Accounts::getSingle(["user_id" => $gt_meta->id, "ac_type" => "regular_thrift"]); 
        if($thrift == null) return false;
        $thriftBalance = Accounts::getBalance($thrift->ac_number);

        // offsets liability from rt balance
        $bal = $thriftBalance + $liability; 
        if($bal < ($loanAmount/2)) {
            return false;
        } else return true;
    }
    
    public static function guarantorLiability($membershipId) {
        $loans = self::guaranteedLoans($membershipId);
        $computedLiability = 0;
        if(Operations::count($loans)) {
            foreach ($loans as $meta) {
                $computedLiability += self::liability($meta);
            }
        }
        if($computedLiability < 0) { // has existing liability
            $computedLiability = $computedLiability/2; // guarantor's share of the liability
        }
        return $computedLiability;
    }

    public static function guaranteedLoans($membershipId) {
        $self = new self;
        $statement = "SELECT * FROM t2w_accounts WHERE
                        `ac_type` = 'loan' AND (
                            JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt1_id')) LIKE ? OR
                            JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt2_id')) LIKE ?
                        ) AND `status` LIKE 'approved'";
        return $self->dbTable->query($statement, 'ss', $membershipId, $membershipId)->execute()->rows();
    }
        
    /**
     * liability
     * This method collates the total liability subjected to a loan facility
     *
     * @param  object $loan
     * @return float
     */
    public static function liability($loan) {
        $loanBal = Accounts::getBalance($loan->ac_number);
        $thrift = Accounts::getSingle(["ac_type" => "regular_thrift", "user_id" => $loan->user_id]);
        if($thrift == null) return $loanBal;
        $thriftBal = Accounts::getBalance($thrift->ac_number);
        $diff = $thriftBal + $loanBal; //difference between loan balance and thrift balance
        if($diff > 1) {
            return 0;
        } else {
            return $diff;
        }
    }
        
    public static function legibility($userId) {
        $loanLegibility = ($option = Options::get('loan_legibility')) ? $option : 6;
    
        $ewallet = Accounts::getSingle(["ac_type" => "contribution", "user_id" => $userId]);
        $loan = Accounts::getSingle(["ac_type" => "loan", "user_id" => $userId]);
        $thrift = Accounts::getSingle(["ac_type" => "regular_thrift", "user_id" => $userId]);
        $user = new User;
        $uMeta = $user->get($userId);
        
        if($ewallet == null) {
            http_response_code(400);
            return "Sorry, you don't have an active e-wallet. Your account is not eligible for loan";
        }

        if($loan != null) {
            if($loan->status == "active" || $loan->status == "in process") {
                http_response_code(400);
                return "You have an active or processing loan. Sorry you cannot request again";
            }

            if($loan->status == "defaulted") {
                http_response_code(400);
                return "You have defaulted in your previous loan request. You cannot request for loan again.";
            }
        }

        $config = new Config();

        $eligibleDate = date_create($ewallet->time_altered, new \DateTimeZone($config->timezone));
        $eligibleDate->modify("+$loanLegibility month");
        $now = date_create("now", new \DateTimeZone($config->timezone));

        if($eligibleDate->getTimestamp() > $now->getTimestamp()) {
            http_response_code(400);
            return "Sorry your account is not up to $loanLegibility months, which qualifies you for loan.";
        }

        if($thrift == null) {
            http_response_code(400);
            return "Sorry, you do not have a regular thrift account. You are not eligible for loan";
        }

        if($thrift->status != "active") {
            http_response_code(400);
            return "Sorry, your thrift account is not currently active. You are not eligible for loan";
        }
        
        $self = new self;
        $credits = $self->dbTable->select("t2w_transactions", "COUNT(id) as count")
                ->where("account", $thrift->ac_number)
                ->where("ledger", "credit")
                ->execute()->row();

        if($credits->count < $loanLegibility) {
            http_response_code(400);
            return "Sorry have not made enough contribution to make you eligible for loan against thrift savings.";
        }

        $percentageLoanable = ($option = Options::get('loanable_sum')) ? $option : 200;
        $thriftBalance = Accounts::getBalance($thrift->ac_number);

        return ($thriftBalance*($percentageLoanable/100) + self::guarantorLiability($uMeta->username));
    }

}

?>