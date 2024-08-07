<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Resources\DbTable;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;

final class Wallets {

    public $dbTable;

    function __construct() {
        $this->dbTable = new DbTable;
    }

    public static function createTable() {
        $self = new self;

        $statement = "CREATE TABLE IF NOT EXISTS t2w_transactions (
                id BIGINT(30) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account VARCHAR(30) NOT NULL,
                `amount` FLOAT(30,2) NOT NULL,
                `ledger` VARCHAR(30) NOT NULL DEFAULT 'credit',
                `status` VARCHAR(30) NOT NULL DEFAULT 'successful',
                `narration` TEXT NOT NULL,
                `meta` JSON NOT NULL,
                time_altered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_altered_by BIGINT(30) NOT NULL
                )";
        $self->dbTable->query($statement)->execute();
    }

    public function new($data, $ledger) {
        extract($data);
        $session = Session::getInstance();
        if($amount == 0) return null;
        $id = $this->dbTable->insert('t2w_transactions', 'sdssssi', [
            "account" => $account,
            "amount" => (double) $amount,
            "ledger" => $ledger,
            "status" => $status ?? "successful",
            "narration" => $narration ?? "Deposit transaction",
            "meta" => json_encode($meta ?? []),
            "last_altered_by" => $session->getResourceOwner()->user_id ?? 0
        ])->execute();
        // send notice
        return $this->dbTable->select('t2w_transactions')
                        ->where('id', (int) $id)
                        ->execute()
                        ->row();
    }

    public static function newCredit(array $data) {
        $self = new self;
        $cr = $self->new($data, "credit");
        if($cr != NULL) {
            \EvoPhp\Actions\Action::do('t2wAfterCredit', $cr);
        }
        return $cr;
    }

    public static function newDebit(array $data) {
        $self = new self;
        return $self->new($data, "debit");
    }

    public static function creditAccount(array $data, string $account, int|NULL $userId = NULL) {
        if($userId != NULL) {
            $accObj = Accounts::getSingle(['ac_type' => $account, 'user_id' => $userId]);
            if($accObj == NULL) return NULL;
            $account = $accObj->ac_number;
        }
        $data['account'] = $account;
        return self::newCredit($data);
    }

    public static function debitAccount(array $data, string $account, int|NULL $userId = NULL) {
        if($userId != NULL) {
            $accObj = Accounts::getSingle(['ac_type' => $account, 'user_id' => $userId]);
            if($accObj == NULL) return NULL;
            $account = $accObj->ac_number;
        }
        $data['account'] = $account;
        return self::newDebit($data);
    }

    public static function reverse($id) {
        $self = new self;
        $txn = $self->dbTable->select('t2w_transactions')
            ->where("id", (int) $id)
            ->execute()->row(0, "ARRAY_A");
        if($txn == null) return null;
        $ledger = $txn->ledger == "credit" ? "debit" : "credit";
        $session = Session::getInstance();
        $fullname = Operations::getFullname($session->getResourceOwner()->user_id);
        $txn->narration = "REVERSAL :: $txn->narration / REF DATE $txn->time_altered by $fullname";
        $txn = (array) $txn;
        unset($txn['ledger']);
        return $self->new($txn, $ledger);
    }
}
?>