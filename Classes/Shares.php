<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\Store;
use EvoPhp\Api\Config;

final class Shares extends Accounts
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function availableShares() {
        $self = new self;
        $buyable = Options::get("available_shares") ?? 10000000;
        $totalBought = $self->dbTable->select("t2w_transactions", "SUM(amount) as total")
            ->where("account", "313%", "s")
            ->where("status", "successful")
            ->execute()->row()->total;
        $available = $buyable - $totalBought;
        return $available < 0 ? 0 : $available; 
    }

    public static function availableSharesToIndividual($user_id) {
        $self = new self;
        $ac = $self::getSingle(["user_id" => $user_id, "ac_type" => "share"]);
        $balance = $ac == NULL ? 0 : $ac->balance;
        $totalAvailable = $self::availableShares();
        $available = (Options::get("max_shares") ?? 4000000) - $balance;
        return $available > $totalAvailable ? $totalAvailable : $available;
    }

    public static function buy($params) {
        $session = Session::getInstance();
        if(!$session->getResourceOwner()) return null;
        extract($params);
        $user_id = $session->getResourceOwner()->user_id;
        $self = new self;
        $account = $self::createAccount(
            $user_id,
            "share",
            true
        );
        $shareUnit = Options::get("share_unit") ?? 1;
        $amount = $shareUnit * $units;
        $contribution = Accounts::getSingle(['ac_type' => 'contribution', 'user_id' => $user_id]);
        if(Accounts::getBalance($contribution->ac_number) < (double) $amount) {
            http_response_code(400);
            return "Insufficient funds in your E-Wallet";
        } else {
            $d = Wallets::debitAccount([
                "amount" => $amount,
                "narration" => "Being payment for $units units of shares"
            ], $contribution->ac_number);

            if($d == NULL) {
                http_response_code(400);
                return "Failed";
            }

            $store = new Store;

            $store->new("buy_share", [
                "units" => $units,
                "amount" => $amount,
                "approval" => 0,
                "user_id" => $user_id
            ]);

            Messages::buyShare($units);

            return $self::getById($account->id);
        }
    }

    public static function sell($params) {
        $session = Session::getInstance();
        if(!$session->getResourceOwner()) return null;
        extract($params);
        $user_id = $session->getResourceOwner()->user_id;
        $self = new self;
        $account = $self::createAccount(
            $user_id,
            "share",
            true
        );
        $shareUnit = Options::get("share_unit") ?? 1;
        $amount = $shareUnit * $units;
        if(Accounts::getBalance($account->ac_number) < (double) $units) {
            http_response_code(400);
            return "You have insufficient units in your shares account to complete this task.";
        } else {
            $d = Wallets::debitAccount([
                "amount" => $units,
                "narration" => "Being units of shares sold."
            ], $account->ac_number);

            if($d == NULL) {
                http_response_code(400);
                return "Failed";
            }

            $store = new Store;

            $store->new("sell_share", [
                "units" => $units,
                "amount" => $amount,
                "approval" => 0,
                "user_id" => $user_id
            ]);

            Messages::sellShare($units);

            return $self::getById($account->id);
        }
    }
}

?>