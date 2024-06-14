<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Resources\Options;
use EvoPhp\Resources\User;
use EvoPhp\Api\Operations;
use EvoPhp\Database\Session;

final class Payout extends Accounts 
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function new(float $amount) {
        $self = new self;
        $user = new User;
        $session = \EvoPhp\Database\Session::getInstance();
        $userId = $session->getResourceOwner()->user_id;
        $account = self::getSingle(["user_id" => $userId, "ac_type" => "contribution"]);
        $balance = self::getBalance($account->ac_number);
        $meta = $user->get($userId);

        if($amount > $balance) {
            http_response_code(400);
            return "Your balance of ".number_format($balance, 2)." Naira is not sufficient to fulfil your request.";
        }

        $bankChargeRecovery = Options::get("bank_charges") ?? 0.5;
        $bankCharges = $amount * ($bankChargeRecovery/100);
        $payoutSum = $amount-$bankCharges;

        $id = $self->dbTable->insert("t2w_ewallet_transactions", "dsssssssi", [
            "amount" => (float) $amount,
            "ledger" => "debit",
            "account" => $account->ac_number,
            "pop" => "#",
            "narration" => "",
            "classification" => "not classified",
            "meta" => json_encode([
                "bank_charge" => $bankCharges,
                "payout_sum" => $payoutSum,
                "ac_number" => $meta->ac_number,
                "ac_name" => $meta->ac_name,
                "bank_name" => $meta->bank_name
            ]),
            "status" => "unconfirmed",
            "last_altered_by" => $session->getResourceOwner()->user_id
        ])->execute();

        Wallets::debitAccount(
            [
                "narration" => "Payout",
                "amount" => $amount
            ], $account->ac_number
        );

        Messages::payoutRequest($session->getResourceOwner()->user_id, $amount);
        return $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
    }

    public static function markAsPaid($params) {
        extract($params);
        $self = new self;
        $session = \EvoPhp\Database\Session::getInstance();
        $txn = $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
        $account = self::getByNumber(["ac_number" => $txn->account]);
        $self->dbTable->update("t2w_ewallet_transactions")
            ->set("status", "confirmed")
            ->set("last_altered_by", $session->getResourceOwner()->user_id)
            ->set("pop", $pop ?? "")
            ->set("classification", $classification ?? "Payout")
            ->metaSet([
                "date_of_payment" => $date_of_payment,
                "mode_of_payment" => $mode_of_payment
            ], [], $id)
            ->where("id", $id, "i")->execute();
        Messages::payoutApproved($account->user_id, $txn->amount, $txn->payout_sum, $txn->bank_charge);
        $fullname = Operations::getFullname($account->user_id);
        $self->log("$fullname's payout request of ".number_format($txn->amount, 2)." was confirmed as paid. 
                A total of ".number_format($txn->payout_sum, 2)." naira was paid and the sum of 
                ".number_format($txn->bank_charge, 2)." was withheld for bank charges recovery and processing fee.");
        return $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
    }

    public static function decline(int $id) {
        $self = new self;
        $session = \EvoPhp\Database\Session::getInstance();
        $txn = $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
        $account = self::getByNumber(["ac_number" => $txn->account]);
        $self->dbTable->update("t2w_ewallet_transactions")
            ->set("status", "declined")
            ->set("last_altered_by", $session->getResourceOwner()->user_id)
            ->where("id", $id, "i")->execute();
        Wallets::creditAccount(
            [
                "narration" => "Rev:: Payout",
                "amount" => $txn->amount
            ], $txn->account
        );
        $fullname = Operations::getFullname($account->user_id);
        $self->log("$fullname's payout request of ".number_format($txn->amount, 2)." was declined.");
        return $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
    }

    public static function okToRequest(int $userId) {
        $self = new self;
        $account = self::getSingle(["user_id" => $userId, "ac_type" => "contribution"]);
        $count = $self->dbTable->select("t2w_ewallet_transactions", "COUNT(id) as count")
                    ->where("ledger", "debit")
                    ->where("account", $account->ac_number)
                    ->where("status", "unconfirmed")
                    ->execute()->row()->count;
        if($count > 0) {
            return ["message" => "Sorry you have a pending payout request. You cannot initiate another", "status" => false];
        }
        $count = $self->dbTable->select("t2w_ewallet_transactions", "COUNT(id) as count")
                    ->where("ledger", "debit")
                    ->where("account", $account->ac_number)
                    ->where("time_altered", date("Y-m-1 00:00:00", time()), false, ">")
                    ->execute()->row()->count;
        $maxMonthlyPayout = Options::get("max_monthly_payout") ?? 1;
        if ($count >= $maxMonthlyPayout) {
            return ["message" => "Sorry you have exceeded your maximum payout requests for the month. Try again next month.", "status" => false];
        } else return ["message" => "", "status" => true];
    }

    public static function postOutflow($data) {
        extract($data);
        $self = new self;
        $session = Session::getInstance();
        $id = $self->dbTable->insert("t2w_inflow_outflow", "dssssssi", [
                "amount" => (double) $amount,
                "ledger" => "debit",
                "pop" => $pop ?? "#",
                "narration" => $narration ?? "",
                "classification" => $classification ?? "Not classified",
                "status" => "unconfirmed",
                "meta" => json_encode([
                    "mode_of_payment" => $mode_of_payment ?? "",
                    "date_of_payment" => $date_of_payment ?? ""
                ]),
                "last_altered_by" => $session->getResourceOwner()->user_id
            ])->execute();
        $self->log("Posted an inflow of $amount narrated as $narration and classified as $classification");
        Messages::outflow($amount, $narration);
        return $self->dbTable->select("t2w_inflow_outflow")->where("id", $id)->execute()->row();
    }

    public static function balance($from = NULL, $to = NULL) {
        $breakDown = (object) self::breakDown($from, $to);
        return $breakDown->deposit - $breakDown->payout;
    }

    public static function breakDown($from = NULL, $to = NULL) {
        $self = new self;
        $q = $self->dbTable->select("t2w_ewallet_transactions", "SUM(amount) as sum")
            ->where("ledger", "credit")
            ->where("status", "confirmed");
            if($from !== NULL) {
                $q->where("time_altered", $from, "s", ">=");
            }
            if($to !== NULL) {
                $q->where("time_altered", $to, "s", "<=");
            }
        $credits = $q->execute()->row()->sum;

        $q = $self->dbTable->select("t2w_ewallet_transactions", "SUM(amount) as sum")
            ->where("ledger", "debit")
            ->where("status", "confirmed");
            if($from !== NULL) {
                $q->where("time_altered", $from, "s", ">=");
            }
            if($to !== NULL) {
                $q->where("time_altered", $to, "s", "<=");
            }
        $debits = $q->execute()->row()->sum;
        
        return ["deposit" => $credits, "payout" => $debits];
    }
}