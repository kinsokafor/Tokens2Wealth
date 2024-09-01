<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\Store;

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
        $store = new Store();
        $pendingBuy = $store->getCount("buy_share")
                            ->where("approval", 0)
                            ->where("user_id", $user_id)
                            ->execute();
        if($pendingBuy > 0) {
            return 0;
        }
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

    public static function approve($id) {
        $self = new self;
        $store = new Store;
        $data = $store::merge($store->get($id)->execute());
        
        switch($data->type) {
            case "buy_share":
                $account = $self::createAccount(
                    $data->user_id,
                    "share",
                    true
                );

                $d = Wallets::creditAccount([
                    "amount" => $data->units,
                    "narration" => "Being units of shares bought."
                ], $account->ac_number);
    
                if($d == NULL) {
                    http_response_code(400);
                    return "Failed";
                }

                $store->update()->metaSet([
                    "approval" => 1
                ], [], $data->id)->where("id", $data->id)->execute();

                Messages::approveBuy($data->user_id, $data->units);

                $self->log(Operations::getFullname($data->user_id)."'s request to buy $data->units units of shares was authorized by admin");
            break;

            case "sell_share":
                $contribution = Accounts::getSingle(['ac_type' => 'contribution', 'user_id' => $data->user_id]);

                $d = Wallets::creditAccount([
                    "amount" => $data->amount,
                    "narration" => "Being value for the units of shares sold."
                ], $contribution->ac_number);
    
                if($d == NULL) {
                    http_response_code(400);
                    return "Failed";
                }

                $store->update()->metaSet([
                    "approval" => 1
                ], [], $data->id)->where("id", $data->id)->execute();

                $self->log(Operations::getFullname($data->user_id)."'s request to sell $data->units units of shares was authorized by admin.");
            break;

            default:
            break;
        }

        return $store->get($id)->execute();
    }

    public static function decline($id) {
        $self = new self;
        $store = new Store;
        $data = $store::merge($store->get($id)->execute());
        
        switch($data->type) {
            case "buy_share":
                $contribution = Accounts::getSingle(['ac_type' => 'contribution', 'user_id' => $data->user_id]);

                $d = Wallets::creditAccount([
                    "amount" => $data->amount,
                    "narration" => "Reversal: Being payment for $data->units units of shares"
                ], $contribution->ac_number);
    
                if($d == NULL) {
                    http_response_code(400);
                    return "Failed";
                }

                $store->delete("buy_share")->where("id", $data->id)->execute();

                Messages::declineBuy($data->user_id, $data->units);

                $self->log(Operations::getFullname($data->user_id)."'s request to sell $data->units units of shares was declined by admin.");
            break;

            case "sell_share":
                $account = $self::createAccount(
                    $data->user_id,
                    "share",
                    true
                );

                $d = Wallets::creditAccount([
                    "amount" => $data->units,
                    "narration" => "Reversal: Being units of shares sold."
                ], $account->ac_number);
    
                if($d == NULL) {
                    http_response_code(400);
                    return "Failed";
                }

                $store->delete("sell_share")->where("id", $data->id)->execute();

                Messages::declineSell($data->user_id, $data->units);

                $self->log(Operations::getFullname($data->user_id)."'s request to buy $data->units units of shares was declined by admin");
            break;

            default:
            break;
        }

        return $store->get($id)->execute();
    }

    public static function bulkCredit($cronId, $amount, $narration) {
        $self = new self;
        $accounts = $self->dbTable->select("t2w_accounts")
                        ->where("ac_type", "share")
                        ->execute()->rows();
        $totalBalance = self::getBalance("313%");
        $membersCount = Operations::count($accounts);
        if($membersCount <= 0 || $totalBalance <= 0) {
            \EvoPhp\Api\Cron::cancel($cronId);
            return;
        }
        $participating_members = [];
        foreach ($accounts as $account) {
            set_time_limit(60);
            $balance = self::getBalance($account->ac_number);
            if($balance <= 0) continue;
            $perc = (100 * $balance)/$totalBalance;
			$creditAmount = $perc * 0.01 * $amount;
            Wallets::creditAccount(
                [
                    "narration" => $narration,
                    "amount" => $creditAmount
                ], "contribution", $account->user_id
            );
            array_push($participating_members, $account->user_id);
        }
        $store = new Store;
        $store->new("bulk_credit", [
            "participating_members" => $participating_members,
            "amount" => (double) $amount,
            "narration" => $narration,
            "mode" => "share_dividends"
        ]);
        $self->log("Super admin Implemented a bulk credit exercise across invested shares and ".Operations::count($participating_members)." accounts were credited. 
        Total sum of NGN ".number_format($amount)." was shared. Thank you");
        \EvoPhp\Api\Cron::cancel($cronId);
        return;
    }
}

?>