<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\User;
use EvoPhp\Resources\Store;

final class ThriftSavings extends Accounts
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function editThriftAmount($id, $amount) {
        $self = new self;
        $session = Session::getInstance();
        $self->dbTable->update("t2w_accounts")
            ->set("last_altered_by", $session->getResourceOwner()->user_id);
        if($amount <= 0) {
            $amount = 0;
            $self->dbTable->set("status", "inactive");
        } else {
            $self->dbTable->set("status", "active");
        }
        $self->dbTable->metaSet(["amount" => $amount, "last_updated" => time()], [], $id, "t2w_accounts")
            ->where("id", (int) $id)->execute();

        $account = self::getById($id);

        $self->log("Updated thrift savings amount for: ".Operations::getFullname($account));

        return $account;
    }

    public static function nextSettlementDate() {
        $now = time();
        $rtsd = Options::get('rt_settlement') ?? 28;
        return date("Y-m-$rtsd 00:00:00", $now);
    }

    public static function new($params) {
        $session = Session::getInstance();
        if(!$session->getResourceOwner()) return null;
        $user_id = $session->getResourceOwner()->user_id;
        extract($params);
        $self = new self;
        $account = $self::createAccount(
            $user_id,
            "regular_thrift",
            true
        );
        $self->dbTable->update('t2w_accounts')
                        ->set('status', 'active')
                        ->metaSet([
                            "amount" => (double) $params['amount'],
                            "last_updated" => time()
                        ], [], $account->id, "t2w_accounts")
                        ->where('id', $account->id)->execute();
        return Accounts::getById($account->id);
    }

    public static function liquidate($id) {
        $session = Session::getInstance();
        $self = new self;

        $account = self::getById($id);

        $thriftBal = self::getBalance($account->ac_number);

        if($thriftBal <= 0) {
            http_response_code(400);
            return "There is no balance in the thrift savings account";
        }

        //check loan liability
        $loanAccount = self::getSingle(["user_id" => $account->user_id, "ac_type" => "loan"]);

        if($loanAccount != NULL) {
            $liability = Loan::liability($loanAccount);
            $thriftBal += $liability;
            if($thriftBal <= 0) {
                http_response_code(400);
                return "Thrift cannot be liquidated due to existing loan liability";
            }
        }

        //check guaranteed loans
        $user = new User;

        $meta = $user->get($account->user_id);

        $guarantorLiability = Loan::guarantorLiability($meta->username);

        $thriftBal = $guarantorLiability + $thriftBal;

        if($thriftBal <= 0) {
            http_response_code(400);
            return "There is nothing to liquidate as guarantor's liability currently outweighs the thrift balance.";
        }

        $pd = new PendingDebits();
        $pendingCredits = $pd->getPendingCredit($account->ac_number);
        if(Operations::count($pendingCredits)) {
            foreach ($pendingCredits as $pc) {
                $self->dbTable->delete("t2w_pending_debits")
                    ->where("id", $pc->id)->execute();
            }
        }

        Wallets::debitAccount(
            [
                "narration" => "Liquidation of regular thrift balance",
                "amount" => $thriftBal
            ], $account->ac_number
        );

        Wallets::creditAccount(
            [
                "narration" => "Liquidation of regular thrift balance",
                "amount" => $thriftBal
            ], "contribution", $account->user_id
        );

        $self->dbTable->update("t2w_accounts")
            ->set("last_altered_by", $session->getResourceOwner()->user_id)
            ->set("status", "inactive")
            ->metaSet(["amount" => 0, "last_updated" => time()], [], $id, "t2w_accounts")
            ->where("id", $id)->execute();

        $account = self::getById($id);

        $self->log("Liquidated thrift savings for: ".Operations::getFullname($account));

        return $account;
    }

    public static function getThriftAccounts() {
        $self = new self;

        return $self->dbTable->select("t2w_accounts")
            ->where("ac_type", "regular_thrift")
            ->where("status", "active")
            ->execute()->rows();
    }

    public static function settle($cronId) {
        $self = new self;

        $refTime = time();
        $refDate = date("M Y", $refTime);
        $settled = $pending = array();

        $accounts = $self::getThriftAccounts();

        if(Operations::count($accounts) <= 0) return;

        foreach ($accounts as $account) {
            $account = $self->dbTable->merge($account);

            $contribution = $self::getSingle(["user_id" => $account->user_id, "ac_type" => "contribution"]);

            $balance = $self::getBalance($contribution->ac_number);

            if($account->amount > $balance) {
                // create pending debit
                PendingDebits::new([
                    "debit_account" => $contribution->ac_number,
                    "credit_account" => $account->ac_number,
                    "narration" => "Back duty due on $refDate. Being regular thrift settlement",
                    "amount" => $account->amount,
                    "category" => "rt",
                ]);

                array_push($pending, $account->user_id);
            } else {
                Wallets::debitAccount(
                    [
                        "narration" => "Regular thrift settlement for the month $refDate",
                        "amount" => $account->amount
                    ], $contribution->ac_number
                );
        
                Wallets::creditAccount(
                    [
                        "narration" => "Regular thrift settlement for the month $refDate",
                        "amount" => $account->amount
                    ], $account->ac_number
                );

                array_push($settled, $account->user_id);
            }
        }

        Messages::thriftSettlement($settled, $pending, $refDate);
    }

    public static function bulkCredit($cronId, $amount, $narration) {
        $self = new self;
        $accounts = $self->dbTable->select("t2w_accounts")
                        ->where("ac_type", "regular_thrift")
                        ->where("status", "active")
                        ->execute()->rows();
        $totalBalance = self::getBalance("312%");
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
            "mode" => "rt_dividends"
        ]);
        $self->log("Super admin Implemented a bulk credit exercise across participating thrift savings account and ".Operations::count($participating_members)." accounts were credited. 
        Total sum of NGN ".number_format($amount)." was shared. Thank you");
        \EvoPhp\Api\Cron::cancel($cronId);
        return;
    }
}

?>