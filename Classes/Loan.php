<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use DateTimeZone;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\User;
use EvoPhp\Resources\Store;
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
        $data = gettype($id) == "object" ? $id : self::getOnServer($id);
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
        $rtsd = Options::get('rt_settlement') ?? 28;
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

    public static function changeGuarantor($params) {
        extract($params);
        $self = new self;
        $loan = Accounts::getById($id);
        if($loan == NULL) {
            http_response_code(400);
            return "Something went wrong";
        }
        $loan = $self::merge($loan);
        $user = new User;
        if(isset($gt1_id)) {
            $meta = $user->get((string) $gt1_id);
            if($meta == null) {
                http_response_code(400);
                return "$gt1_id is not a valid membership ID.";
            }
            if($loan->gt1_approval == "declined" && $loan->gt1_id == $gt1_id) {
                http_response_code(400);
                return "Please get another cooperator for your first guarantor. The person you submitted recently declined to suretee you.";
            }
            $verified = self::verifyGuarantor($meta, $loan->amount);
            if($verified !== true) {
                return $verified;
            }
            $metaSet["gt1_id"] = $gt1_id;
            $metaSet["gt1_fullname"] = $gt1_fullname;
            $metaSet["gt1_approval"] = "pending";
            if($gt1_id != $loan->gt1_id)
                Messages::guarantorNomination($meta->id);
        }
        if(isset($gt2_id)) {
            if($gt2_id == ($gt1_id ?? $loan->gt1_id)) {
                http_response_code(400);
                return "You used the same guarantor.";
            }
            $meta = $user->get((string) $gt2_id);
            if($meta == null) {
                http_response_code(400);
                return "$gt2_id is not a valid membership ID.";
            }
            if($loan->gt2_approval == "declined" && $loan->gt2_id == $gt2_id) {
                http_response_code(400);
                return "Please get another cooperator for your second guarantor. The person you submitted recently declined to suretee you.";
            }
            $verified = self::verifyGuarantor($meta, $loan->amount);
            if($verified !== true) {
                return $verified;
            }
            $metaSet["gt2_id"] = $gt2_id;
            $metaSet["gt2_fullname"] = $gt2_fullname;
            $metaSet["gt2_approval"] = "pending";
            if($gt2_id != $loan->gt2_id)
                Messages::guarantorNomination($meta->id);
        }
        $self->dbTable->update("t2w_accounts")
            ->metaSet($metaSet, [], $id, "t2w_accounts")
            ->where("id", $id)
            ->execute();

        return Accounts::getById($id);
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

        $loan = Accounts::getById($account->id);
        if(!isset($gt1_id) && !isset($gt2_id)) {
            Messages::newLoan($loan);
        }
        return $loan;
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

    public static function pendingGuaranteedLoans($membershipId) {
        $self = new self;
        $statement = "SELECT t1.id, user_id, ac_number, ac_type, meta, 
            time_altered, last_altered_by, t2.email, t2.username, t2.usermeta 
            FROM t2w_accounts AS t1
            LEFT JOIN
            (SELECT id, email, username, meta as usermeta FROM users) as t2
            ON t2.id = t1.user_id WHERE
            `ac_type` = 'loan' AND (
                (JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt1_id')) LIKE ? AND
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt1_approval')) LIKE 'pending') 
                OR
                (JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt2_id')) LIKE ? AND
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt2_approval')) LIKE 'pending')
            ) AND `status` LIKE 'in process'
            ";
        return $self->dbTable->query($statement, 'ss', $membershipId, $membershipId)->execute()->rows();
    }

    public static function approvedGuaranteedLoans($membershipId) {
        $self = new self;
        $statement = "SELECT t1.id, user_id, ac_number, ac_type, meta, status, 
            time_altered, last_altered_by, t2.email, t2.username, t2.usermeta 
            FROM t2w_accounts AS t1
            LEFT JOIN
            (SELECT id, email, username, meta as usermeta FROM users) as t2
            ON t2.id = t1.user_id WHERE
            `ac_type` = 'loan' AND (
                (JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt1_id')) LIKE ? AND
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt1_approval')) LIKE 'approved') 
                OR
                (JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt2_id')) LIKE ? AND
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt2_approval')) LIKE 'approved')
            )
            ";
        return $self->dbTable->query($statement, 'ss', $membershipId, $membershipId)->execute()->rows();
    }

    public static function guarantorAccept($id, $membershipId) {
        $self = new self;
        $loan = Accounts::getById($id);
        if($loan == NULL) {
            http_response_code(400);
            return "Invalid submission";
        }
        $loan = self::merge($loan);
        $self->dbTable->update("t2w_accounts");
        if($loan->gt1_id == $membershipId) {
            $self->dbTable->metaSet(
                ["gt1_approval" => "approved"], [], $id, "t2w_accounts");
        }
        if($loan->gt2_id == $membershipId) {
            $self->dbTable->metaSet(
                ["gt2_approval" => "approved"], [], $id, "t2w_accounts");
        }
        $self->dbTable->where("id", $loan->id)->execute();
        $loan = Accounts::getById($id);
        $loan = self::merge($loan);
        if($loan->gt1_approval == "approved" && $loan->gt2_approval == "approved") {
            Messages::guarantorsAccepted($loan);
        }
        return $loan;
    }

    public static function guarantorDecline($id, $membershipId) {
        $self = new self;
        $loan = Accounts::getById($id);
        if($loan == NULL) {
            http_response_code(400);
            return "Invalid submission";
        }
        $loan = self::merge($loan);
        $self->dbTable->update("t2w_accounts");
        if($loan->gt1_id == $membershipId) {
            $self->dbTable->metaSet(
                ["gt1_approval" => "declined"], [], $id, "t2w_accounts");
        }
        if($loan->gt2_id == $membershipId) {
            $self->dbTable->metaSet(
                ["gt2_approval" => "declined"], [], $id, "t2w_accounts");
        }
        $self->dbTable->where("id", $loan->id)->execute();
        Messages::guarantorsDeclined($loan);
        $loan = Accounts::getById($id);
        $loan = self::merge($loan);
        return $loan;
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
            if($loan->status == "active" || $loan->status == "approved" || $loan->status == "in process") {
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

    public static function pendingLoanCount() {
        $self = new self;
        $statement = "SELECT COUNT(id) AS count 
            FROM t2w_accounts 
            WHERE
            `ac_type` = 'loan' AND (
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt1_approval')) LIKE 'approved' 
                AND
                JSON_UNQUOTE(JSON_EXTRACT(meta, '$.gt2_approval')) LIKE 'approved'
            ) AND `status` LIKE 'in process'
        ";
        return $self->dbTable->query($statement)->execute()->row()->count;
    }

    public static function approve($id) {
        $self = new self;
        $loan = $self::getById($id);
        $loan = $self::merge($loan);
        if($loan->status != "in process") {
            http_response_code(400);
            return "Something went wrong";
        }
        $percCharge = Options::get("percentage_charge_on_loan") ?? 1;
        $charge = 0.01 * $percCharge * $loan->amount;
        $interest = $self::getInterest((object) [
            "rate" => Options::get("loan_rate"),
            "tenure" => $loan->tenure,
            "amount" => $loan->amount
        ]);
        Wallets::debitAccount(
            [
                "narration" => "Being sum approved for loan plus the interest calculated against $loan->rate % rate",
                "amount" => ($interest + $loan->amount)
            ], $loan->ac_number
        );
        Wallets::creditAccount(
            [
                "narration" => "Being sum approved for loan",
                "amount" => $loan->amount
            ], "contribution", $loan->user_id
        );
        Wallets::debitAccount(
            [
                "narration" => "Being sum charged at $percCharge % rate of total loan amount.",
                "amount" => $charge
            ], "contribution", $loan->user_id
        );
        $config = new Config();
        $d = new \DateTime('now', new DateTimeZone($config->timezone));
        $session = Session::getInstance();
        $self->dbTable->update("t2w_accounts")
            ->set("status", "approved")
            ->set("time_altered", $d->format('Y-m-d h:i:s'))
            ->set("last_altered_by", $session->getResourceOwner()->user_id)
            ->metaSet([
                "rate" => $loan->rate,
                "moratorium" => Options::get("moratorium") ?? 1,
                "amount" => (float) $loan->amount,
                "tenure" => $loan->tenure,
                "plan" => "Against Thrift Savings",
                "repayment_sum" => ($interest + $loan->amount)
            ], [], $loan->id, "t2w_accounts")
            ->where("id", $loan->id)->execute();
        $store = new Store;
        $store->new("loan", [
            "amount" => (float) $loan->amount,
            "repayment" => ($interest + $loan->amount),
            "beneficiary" => $loan->user_id,
            "rate" => $loan->rate,
            "status" => 'pending',
            "tenure" => $loan->tenure
        ]);
        Messages::loanApproved($loan);
        $self->log(Operations::getFullname($loan->user_id)."'s loan request approved.");
        return $self::getById($id);
    }

    public static function decline($id) {
        $self = new self;
        $loan = $self::getById($id);
        $self->dbTable->update("t2w_accounts")
            ->set("status", "declined")
            ->metaSet([
                "rate" => 0,
                "moratorium" => 1,
                "amount" => 0,
                "tenure" => 0,
                "plan" => "",
                "repayment_sum" => 0
            ], [], $loan->id, "t2w_accounts")
            ->where("id", $loan->id)->execute();
        Messages::loanDeclined($loan);
        $self->log(Operations::getFullname($loan->user_id)."'s loan request declined.");
    }

    public static function terminateAfterCredit($cr) {
        if(substr($cr->account, 0, 2) != '321') return;
        $balance = Accounts::getBalance($cr->account);
        if($balance < 0) {
            return;
        }
        self::terminate($cr->account);
    }

    public static function terminate($ac_number) {
        set_time_limit(200);
        $self = new self;
        $account = $self::getByNumber(["ac_number" => $ac_number]);
        $account = $self->dbTable::merge($account);
        $update = $self->dbTable->update("t2w_accounts")
                        ->metaSet([
                            'gt1_id' => '',
                            'gt2_id' => '',
                            'gt1_approval' => '',
                            'gt2_approval' => '',
                            'level' => '',
                            'gt1_fullname' => '',
                            'gt2_fullname' => '',
                            'rate' => '',
                            'plan' => '',
                            'amount' => 0
                        ], [], $account->id, "t2w_accounts");
        if($account->status != "defaulted") {
            $update->set("status", "cleared", "s");
        }
        $update->where("id", $account->id)->execute();
        $store = new Store;
        $loan = $store->select("loan")
                    ->where("beneficiary", $account->user_id)
                    ->where("status", "pending")
                    ->execute()->row();
        if($loan != null) {
           $store->update()->metaSet([
                'status' => 'cleared', 
                'cleared_on' => time(), 
                'tenure' => $account->tenure, 
                'rate' => $account->rate
            ], [], $loan->id)->where("id", $loan->id)->execute(); 
        }
        
        $pd = new PendingDebits();
        $pendingCredits = $pd->getPendingCredit($account->ac_number, "loan");
        if(Operations::count($pendingCredits)) {
            foreach ($pendingCredits as $pc) {
                $self->dbTable->delete("t2w_pending_debits")
                    ->where("id", $pc->id)->execute();
            }
        }
        Messages::terminateLoan($account);
        return $self::getByNumber(["ac_number" => $ac_number]);
    }

    public static function settleBalance($ac_number, int|NULL $userId = NULL) {
        $self = new self;
        $loanBalance = $self::getBalance($ac_number);
        if($userId == NULL) {
            $account = $self::getByNumber(["ac_number" => $ac_number]);
            $userId = $account->user_id;
        }
        $contribution = $self::getSingle(["user_id" => $userId, "ac_type" => "contribution"]);
        $balance = $self::getBalance($contribution->ac_number);
        if(($balance + $loanBalance) < 0) {
            http_response_code(400);
            return "The balance in the e-wallet is not sufficient to clear the loan balance";
        }
        $amount = $loanBalance * -1;
        Wallets::debitAccount(
            [
                "narration" => "Being sum deducted to clear your loan balance.",
                "amount" => $amount
            ], $contribution->ac_number
        );

        Wallets::creditAccount(
            [
                "narration" => "Being sum credited to clear your loan balance.",
                "amount" => $amount
            ], $ac_number
        );

        return $self::terminate($ac_number);
    }

    public static function recover($ac_number, int|NULL $userId = NULL) {
        $self = new self;
        $loanBalance = $checkAmount = $self::getBalance($ac_number);
        if($userId == NULL) {
            $account = $self::getByNumber(["ac_number" => $ac_number]);
            $userId = $account->user_id;
        }
        $checkAmount = $self::loanRecoveryProcess($userId, $checkAmount, $ac_number);
        Messages::loanRecovery($userId, $loanBalance, $checkAmount);
        if($checkAmount < 0) {
            $gtPenalty = $checkAmount/2;
            $meta = $self::getByNumber(["ac_number" => $ac_number]);
            $meta = $self->dbTable::merge($meta);
            $user = new User;
            if(isset($meta->gt1_id) && $meta->gt1_id != "NA" && $meta->gt1_id != "") {
                $gtMeta = $user->get((string) $meta->gt1_id);
                $gt1Penalty = $self::loanRecoveryProcess($gtMeta->id, $gtPenalty, $ac_number);
                if($gt1Penalty < 0) {
                    $gtPenalty += $gt1Penalty;
                }
                Messages::loanRecoveryGT($userId, $gtMeta, $gtPenalty, $gt1Penalty);
            }
            if(isset($meta->gt2_id) && $meta->gt2_id != "NA" && $meta->gt2_id != "") {
                $gtMeta = $user->get((string) $meta->gt2_id);
                $checkAmount = $self::loanRecoveryProcess($gtMeta->id, $gtPenalty, $ac_number);
                Messages::loanRecoveryGT($userId, $gtMeta, $gtPenalty, $gt1Penalty);
            }
        }
        $pd = new PendingDebits();
        $pendingCredits = $pd->getPendingCredit($ac_number, "loan");
        if(Operations::count($pendingCredits)) {
            foreach ($pendingCredits as $pc) {
                $self->dbTable->delete("t2w_pending_debits")
                    ->where("id", $pc->id)->execute();
            }
        }
        if(round($checkAmount, 2) >= 0) {
            return self::terminate($ac_number);
        } else {
            $amount = -1 * $checkAmount;
            $ewallet = self::getSingle(["user_id" => $userId, "ac_type" => "contribution"]);
            $pd::new([
                'amount' => $amount,
                'narration' => 'Back duty due on '.date('d M, Y', time()).'. Being loan recovery against regular thrift',
                'credit_account' => $ac_number,
                'debit_account' => $ewallet->ac_number,
                'category' => 'loan'
            ]);
        }
        return $self::getByNumber(["ac_number" => $ac_number]);
    }

    public static function loanRecoveryProcess($userId, $checkAmount, $loanAccount) {

        //clear ewallet
        $ewallet = self::getSingle(["user_id" => $userId, "ac_type" => "contribution"]);
        if($ewallet != null) {
            $checkAmount = self::settleLoanRecovery($ewallet->ac_number, $checkAmount, $loanAccount);
        }
        
        //clear regular thrift
        $thrift = self::getSingle(["user_id" => $userId, "ac_type" => "regular_thrift"]);
        if($thrift != null) {
            $checkAmount = self::settleLoanRecovery($thrift->ac_number, $checkAmount, $loanAccount);
        }
        
        //liquidate term_deposit
        $termDeposit = self::getSingle(["user_id" => $userId, "ac_type" => "term_deposit"]);
        if(($termDeposit != null) && $checkAmount < 0) {
            if(self::getBalance($termDeposit->ac_number) > 0) {
                TermDeposit::liquidate($termDeposit->id);
            }
        }

        //clear ewallet
        //after liquidating term deposit
        if($ewallet != null) {
            $checkAmount = self::settleLoanRecovery($ewallet->ac_number, $checkAmount, $loanAccount);
        }

        //clear lien
        if($ewallet != null) {
            $checkAmount = self::settleLoanRecoveryByLien($ewallet, $checkAmount, $loanAccount);
        }

        return $checkAmount;
    }

    public static function settleLoanRecovery($ac_number, $checkAmount, $loanAccount) {
        set_time_limit(200);
        if($checkAmount >= 0)
            return $checkAmount;
        $balance = self::getBalance($ac_number);
        if($balance > 0) {
            if(($balance + $checkAmount) < 0) {
                $checkAmount += $balance;
                Wallets::debitAccount(
                    [
                        "narration" => "Loan recovery ifo $loanAccount",
                        "amount" => $balance
                    ], $ac_number
                );
        
                Wallets::creditAccount(
                    [
                        "narration" => "Loan recovery ifo $ac_number",
                        "amount" => $balance
                    ], $loanAccount
                );
            } else {
                $diff = $balance + $checkAmount;
                $debitSum = $balance - $diff;
                Wallets::debitAccount(
                    [
                        "narration" => "Loan recovery ifo $loanAccount",
                        "amount" => $debitSum
                    ], $ac_number
                );
        
                Wallets::creditAccount(
                    [
                        "narration" => "Loan recovery from $ac_number",
                        "amount" => $debitSum
                    ], $loanAccount
                );
                $checkAmount += $debitSum;
            }
        }
        return $checkAmount;
    }

    public static function settleLoanRecoveryByLien($account, $checkAmount, $loanAccount) {
        $self = new self;
        set_time_limit(200);
        if($checkAmount > 0)
            return $checkAmount;
        $account = $self->dbTable::merge($account);
        $lienBal = $account->lien_bal ?? 0;
        if($lienBal > 0) {
            if(($lienBal + $checkAmount) < 0) {
                $self->dbTable->update("t2w_accounts")
                    ->metaSet([
                        'lien_bal' => 0
                    ], [], $account->id, "t2w_accounts")
                    ->where("id", $account->id)->execute();
                Wallets::creditAccount(
                    [
                        "narration" => "Loan recovery from lien",
                        "amount" => $lienBal
                    ], $loanAccount
                );
                $checkAmount += $lienBal;
            } else {
                $diff = $lienBal + $checkAmount;
                $debitSum = $lienBal - $diff;
                $self->dbTable->update("t2w_accounts")
                    ->metaSet([
                        'lien_bal' => $diff
                    ], [], $account->id, "t2w_accounts")
                    ->where("id", $account->id)->execute();
                Wallets::creditAccount(
                    [
                        "narration" => "Loan recovery from lien",
                        "amount" => $debitSum
                    ], $loanAccount
                );
                $checkAmount += $debitSum;
            }
        }
        return $checkAmount;
    }

    public static function getLoanAccounts() {
        $self = new self;

        return $self->dbTable->select("t2w_accounts")
            ->where("ac_type", "loan")
            ->whereIn("status", "s", "approved", "defaulted")
            ->execute()->rows();
    }

    public static function settle($cronId) {
        $config = new Config;

        $self = new self;

        $user = new User;

        $refTime = time();
        
        $refDate = date("M Y", $refTime);

        $rtsd = ThriftSavings::nextSettlementDate();

        $rtsdTime = date_create($rtsd, new DateTimeZone($config->timezone));

        $badLoan = Options::get("bad_loan") ?? 3;

        $settled = $pending = array();

        $accounts = $self::getLoanAccounts();

        if(Operations::count($accounts) <= 0) return;

        foreach ($accounts as $account) {
            set_time_limit(200);
            $balance = self::getBalance($account->ac_number);
            if(round($balance) >= 0) continue;

            $account = $self->dbTable->merge($account);

            $moratorium = $account->moratorium ?? 0;

            $dateFrom = date_create($account->time_altered, new DateTimeZone($config->timezone));
            if($moratorium > 0) {
                $interval = new \DateInterval('P'.$moratorium.'M');
                $dateFrom->add($interval);
            }
            if($rtsdTime->getTimestamp() > $dateFrom->getTimestamp()) {
                //settle
                $monthsSpent = self::monthsSpent($account, $rtsd);
                $remainingMonths = $account->tenure - $monthsSpent;
                if($remainingMonths > 1) {
                    $debitAmount = ($balance * -1)/$remainingMonths;
                } else $debitAmount = $balance * -1;

                $contribution = $self::getSingle(["user_id" => $account->user_id, "ac_type" => "contribution"]);
                $contributionBalance = $self::getBalance($contribution->ac_number);

                if($debitAmount > $contributionBalance) {
                    // create pending debit
                    $sumPC = PendingDebits::pendingCreditSum($account->ac_number);
                    $countPC = PendingDebits::pendingCreditCount($account->ac_number);

                    if($countPC >= $badLoan) {
                        // defaulted
                        $self->dbTable->update("t2w_accounts")
                            ->set("status", "defaulted")
                            ->where("id", $account->id)
                            ->execute();
                    }

                    if(($balance + $sumPC + $debitAmount) >= 0) {
                        $debitAmount = 0 - ($balance + $sumPC);
                        if($debitAmount == 0) continue;
                    }

                    PendingDebits::new([
                        "debit_account" => $contribution->ac_number,
                        "credit_account" => $account->ac_number,
                        "narration" => "Back duty due on $refDate. Being Loan repayment against e-wallet.",
                        "amount" => $debitAmount,
                        "category" => "loan",
                    ]);
    
                    array_push($pending, [
                        "user" => $user->get($account->user_id),
                        "loan_balance" => number_format($balance, 2),
                        "wallet_balance" => number_format($contributionBalance, 2),
                        "sum_pc" => number_format($sumPC, 2),
                        "count_pc" => number_format($countPC),
                        "amount" => number_format($debitAmount)
                    ]);
                } else {
                    Wallets::debitAccount(
                        [
                            "narration" => "Loan settlement for the month $refDate",
                            "amount" => $debitAmount
                        ], $contribution->ac_number
                    );
            
                    Wallets::creditAccount(
                        [
                            "narration" => "Loan settlement for the month $refDate",
                            "amount" => $debitAmount
                        ], $account->ac_number
                    );

                    array_push($settled, $account->user_id);
                }
            }
        }

        Messages::loanSettlement($settled, $pending, $refDate);

    }
}

?>