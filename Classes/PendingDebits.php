<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Resources\DbTable;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Api\Config;

final class PendingDebits
{

    public $dbTable;

    function __construct() {
        $this->dbTable = new DbTable;
    }

    public static function createTable() {
        $self = new self;

        $statement = "CREATE TABLE IF NOT EXISTS t2w_pending_debits (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                debit_account VARCHAR(30) NOT NULL,
                credit_account VARCHAR(30) NOT NULL,
                narration TEXT NOT NULL,
                `amount` FLOAT(30,2) NOT NULL,
                `category` VARCHAR(30) NOT NULL DEFAULT 'rt',
                `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
                `meta` JSON NOT NULL,
                time_altered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_altered_by BIGINT NOT NULL
                )";
        $self->dbTable->query($statement)->execute();
    }

    public static function new(array $data) {
        $data = array_merge([
            "category" => "rt",
            "status" => "pending",
            "narration" => "",
            "meta" => []
        ], $data);
        extract($data);
        if($amount <= 0) {
            return null;
        }
        $self = new self;
        $session = Session::getInstance();
        $id = $self->dbTable->insert("t2w_pending_debits", "sssdsssi", [
            "debit_account" => $debit_account,
            "credit_account" => $credit_account,
            "narration" => $narration,
            "amount" => (double) $amount,
            "category" => $category,
            "status" => $status,
            "meta" => json_encode($meta),
            "last_altered_by" => $session->getResourceOwner() ? (int) $session->getResourceOwner()->user_id : 0
        ])->execute();
        $pd = $self->dbTable->select("t2w_pending_debits")->where("id", $id)->execute()->row();
        if($pd != NULL) {
            Messages::pendingDebit($pd);
        }
        return $pd;
    }

    public function hasPendingDebit(string $ac_number, string|NULL $category = NULL) {
        $this->dbTable->select('t2w_pending_debits', 'COUNT(id) as count')
                        ->where('status', 'pending')
                        ->where('debit_account', $ac_number);
        if($category != NULL) {
            $this->dbTable->where('category', $category);
        }
        $count = $this->dbTable->execute()->row()->count;
        return $count > 0 ? true : false;
    }

    public function getPendingDebit(string $ac_number, string|NULL $category = NULL) {
        $this->dbTable->select('t2w_pending_debits')
                        ->where('status', 'pending')
                        ->where('debit_account', $ac_number);
        if($category != NULL) {
            $this->dbTable->where('category', $category);
        }
        return $this->dbTable->execute()->rows();
    }

    public function getPendingCredit(string $ac_number, string|NULL $category = NULL) {
        $this->dbTable->select('t2w_pending_debits')
                        ->where('status', 'pending')
                        ->where('credit_account', $ac_number);
        if($category != NULL) {
            $this->dbTable->where('category', $category);
        }
        return $this->dbTable->execute()->rows();
    }

    public static function handle($cr) {
        $self = new self;
        $self->handlePendingDebits($cr->account);
    }
    
    public function handlePendingDebits($ac_number, string|NULL $category = NULL) {
        ignore_user_abort(true);
        ini_set('max_execution_time', 300);
        $pd = $this->getPendingDebit($ac_number, $category);
        if($pd != NULL) {
            foreach ($pd as $value) {
                $this->handlePendingDebit($value);
            }
        }
    }
    
    function handlePendingDebit($pd) {
        $session = Session::getInstance();
        $config = new Config();
        set_time_limit(200);
        if($pd->amount > Accounts::getBalance($pd->debit_account)) 
            return false;
        
        $debit = Wallets::newDebit([
            'narration' => $pd->narration,
            'amount' => $pd->amount,
            'account' => $pd->debit_account
        ]);

        if($debit == NULL) return false;
        $credit = Wallets::newCredit([
            'narration' => $pd->narration,
            'amount' => $pd->amount,
            'account' => $pd->credit_account
        ]);

        if($credit == NULL) {
            $this->dbTable->delete('t2w_transactions')->where('id', $debit->id)->execute();
            return false;
        }

        $d = date_create('now', new \DateTimeZone($config->timezone));
        $this->dbTable->update('t2w_pending_debits')
                        ->set('status', 'paid')
                        ->set('last_altered_by', $session->getResourceOwner() ? (int) $session->getResourceOwner()->user_id : 0)
                        ->set('time_altered', $d->format('Y-m-d H:i:s'))
                        ->where('id', $pd->id)->execute(); 
    }

    public static function pendingDebitCount($account) {
        $self = new self;

        return $self->dbTable->select('t2w_pending_debits', 'COUNT(id) as count')
                            ->where('debit_account', $account)
                            ->where('status', 'pending')
                            ->execute()->row()->count;
    }

    public static function pendingDebitSum($account) {
        $self = new self;

        return $self->dbTable->select('t2w_pending_debits', 'IFNULL(SUM(amount), 0) as sum')
                            ->where('debit_account', $account)
                            ->where('status', 'pending')
                            ->execute()->row()->sum;
    }

    public static function pendingCreditCount($account) {
        $self = new self;

        return $self->dbTable->select('t2w_pending_debits', 'COUNT(id) as count')
                            ->where('credit_account', $account)
                            ->where('status', 'pending')
                            ->execute()->row()->count;
    }

    public static function pendingCreditSum($account) {
        $self = new self;

        return $self->dbTable->select('t2w_pending_debits', 'IFNULL(SUM(amount), 0) as sum')
                            ->where('credit_account', $account)
                            ->where('status', 'pending')
                            ->execute()->row()->sum;
    }
}

?>