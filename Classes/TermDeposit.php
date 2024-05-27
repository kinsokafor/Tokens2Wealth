<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Api\Config;

final class TermDeposit extends Accounts
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function new($params) {
        $session = Session::getInstance();
        if(!$session->getResourceOwner()) return null;
        extract($params);
        $user_id = $session->getResourceOwner()->user_id;
        $self = new self;
        $account = $self::createAccount(
            $user_id,
            "term_deposit",
            true
        );
        $contribution = Accounts::getSingle(['ac_type' => 'contribution', 'user_id' => $user_id]);
        if(Accounts::getBalance($contribution->ac_number) < (double) $amount) {
            http_response_code(400);
            return "Insufficient funds in your E-Wallet";
        } else {
            $d = Wallets::debitAccount([
                "amount" => $amount,
                "narration" => "A new debit from your e-wallet $contribution->ac_number to fund your term deposit $account->ac_number"
            ], $contribution->ac_number);

            if($d == NULL) {
                http_response_code(400);
                return "Failed";
            }

            $c = Wallets::creditAccount([
                "amount" => $amount,
                "narration" => "A new credit from your e-wallet $contribution->ac_number to fund your term deposit $account->ac_number"
            ], $account->ac_number);

            if($c == NULL) {
                $self->dbTable->delete('t2w_transactions')->where('id', $d->id)->execute();
                http_response_code(400);
                return "Failed";
            }
        }
        $td_rate = Options::get("td_rate");
        $self->dbTable->update('t2w_accounts')
                        ->set('status', 'pending')
                        ->metaSet([
                            "td_tenure" => $tenure,
                            "td_rate" => $td_rate
                        ], [], $account->id, "t2w_accounts")
                        ->where('id', $account->id)->execute();
        Messages::newTermDeposit($account);              
        return Accounts::getById($account->id);
    }

    public static function approve($id) {
        $self = new self;
        $data = $self::getById($id);
        if($data == NULL) {
            http_response_code(400);
            return "Invalid submission";
        }
        extract((array) $data);
        $meta = (array) json_decode($meta);
        extract($meta);
        $config = new Config();
        $d = date_create('now', new \DateTimeZone($config->timezone));
        $tenure_begins = $d->getTimestamp();
        $amount = $self::getBalance($ac_number);
        $maturity = $tenure_begins + (60*60*24*30*$td_tenure);
        $self->dbTable->update("t2w_accounts")
            ->set("status", "active")
            ->metaSet([
                "tenure_begins" => $tenure_begins,
                "maturity" => $maturity
            ], [], $id, "t2w_accounts")
            ->where("id", $id)->execute();
        $emv = number_format(self::estimatedMaturityValue($amount, $td_rate, $td_tenure), 2);
        $self->log(
            Operations::getFullname($user_id).' term deposit request of NGN '
            .number_format($amount, 2).' for the period of '.$td_tenure.
            ' month(s) has been approved to mature on '.date('Y-m-d', $maturity).'.
             This deposit was approved at the interest rate of '.$td_rate.'% per annum. 
             Estimated maturity value is: NGN '
             .$emv
            );
        Messages::approvedTermDeposit(number_format($amount, 2), $emv, $maturity, $user_id);
        return $self::getById($id);
    }

    public static function estimatedMaturityValue($amount, $rate, $tenure) {
        $amount = (float) $amount;
        $rate = (float) $rate;
        $tenure = (int) $tenure;
        $interest = self::dailyInterest($amount, $rate) * ($tenure*30);
        return ($amount+$interest);
    }

    public static function dailyInterest($amount, $rate) {
        return ($rate*$amount)/36000;
    }

    public static function liquidate($id, $withInterest = false, $intent = "Liquidated") {
        ignore_user_abort(true); // just to be safe
        set_time_limit(200);
        $self = new self;
        $data = $self::getById($id);
        if($data == NULL) {
            http_response_code(400);
            return "Invalid submission";
        }
        extract((array) $data);
        $meta = (array) json_decode($meta);
        extract($meta);
        $config = new Config();
        $amount = $self::getBalance($ac_number);
        $premature = false;
        if(!isset($maturity)) {
            $premature = true;
        }
        $d = date_create("now", new \DateTimeZone($config->timezone));
        if($maturity > $d->getTimestamp()) {
            $premature = true;
        }
        Wallets::debitAccount(
            [
                "narration" => "Term deposit principal liquidation",
                "amount" => $amount
            ], $ac_number
        );
        Wallets::creditAccount(
            [
                "narration" => "Term deposit principal liquidation",
                "amount" => $amount
            ], "contribution", $user_id
        );
        $self->log(Operations::getFullname($user_id).' term deposit was liquidated');
        if(!$premature || $withInterest) {
            Wallets::creditAccount(
                [
                    "narration" => "Term deposit interest liquidation",
                    "amount" => $interest_earned ?? 0
                ], "contribution", $user_id
            );
        }
        $self->dbTable->update("t2w_accounts")
            ->set("status", "liquidated")
            ->metaSet([
                "interest_earned" => 0,
                "maturity" => "",
                "td_rate" => "",
                "td_tenure" => ""
            ], [], $id, "t2w_accounts")
            ->where("id", $id)->execute();
        $fullname = Operations::getFullname($user_id);
        $self->log("$intent $fullname's term deposit");
        Messages::termDepositLiquidation(
            $fullname, 
            number_format($amount, 2), 
            $premature, 
            $withInterest, 
            number_format($interest_earned ?? 0, 2),
            $intent
        );
        return $self::getById($id);
    }

    public static function modify($data) {
        extract($data);
        $self = new self;
        $self->dbTable->update("t2w_accounts")
            ->metaSet(["td_rate" => $td_rate], [], $id, "t2w_accounts")
            ->where("id", $id)
            ->execute();

        return $self::getById($id);
    }

    public static function topUp(float $amount) {
        $self = new self;
        $session = Session::getInstance();
        if($session->getResourceOwner() == null) {
            http_response_code(400);
            return "Invalid session. Log out and login again";
        }
        $user_id = $session->getResourceOwner()->user_id;
        $contribution = $self::getSingle(["user_id" => $user_id, "ac_type" => "contribution"]);
        $balance = $self::getBalance($contribution->ac_number);
        if($amount > $balance) {
            http_response_code(400);
            return "Insufficient balance in your e-wallet. Your e-wallet balance is NGN ".number_format($balance, 2).".";
        }
        Wallets::debitAccount(
            [
                "narration" => "Term deposit top-up",
                "amount" => $amount
            ], $contribution->ac_number
        );
        Wallets::creditAccount(
            [
                "narration" => "Term deposit top-up",
                "amount" => $amount
            ], "term_deposit", $user_id
        );
    }
}

?>