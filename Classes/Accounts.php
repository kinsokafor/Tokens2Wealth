<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Resources\DbTable;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;

class Accounts
{
    use AdminLog;

    public $dbTable;

    private $statement = "
    SELECT id, user_id, ac_number, ac_type, status, meta, 
        time_altered, last_altered_by, 
        (IFNULL(t2.credits, 0) - IFNULL(t3.debits, 0)) as balance, 
        IFNULL(t2.credits, 0) as credits, IFNULL(t3.debits, 0) as debits FROM t2w.t2w_accounts as t1 
    LEFT JOIN 
        (   SELECT IFNULL(SUM(amount), 0) as credits, account 
        FROM t2w.t2w_transactions WHERE ledger = 'credit' AND status = 'successful' GROUP BY account) as t2
    ON t1.ac_number LIKE t2.account
    LEFT JOIN 
        (   SELECT IFNULL(SUM(amount), 0) as debits, account 
        FROM t2w.t2w_transactions WHERE ledger = 'debit' AND status = 'successful' GROUP BY account) as t3
    ON t1.ac_number LIKE t3.account";

    function __construct() {
        $this->dbTable = new DbTable;
    }

    public static function createTable() {
        $self = new self;

        $statement = "CREATE TABLE IF NOT EXISTS t2w_accounts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                ac_number VARCHAR(30) NOT NULL,
                `ac_type` VARCHAR(30) NOT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `meta` JSON NOT NULL,
                time_altered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_altered_by BIGINT NOT NULL
                )";
        $self->dbTable->query($statement)->execute();
    }

    public static function getOnServer($id) {
        $self = new self;
        $data = $self->dbTable->select('t2w_accounts')
                    ->where('id', (int) $id)
                    ->execute()->row();
        if($data == null) return null;
        $meta = json_decode($data->meta);
        $data = array_merge((array) $data, (array) $meta);
        unset($data['meta']);
        return (object) $data;
    }

    public static function getBreakdown($ac_number, $upto = NULL) {
        $self = new self;
        $upto = $upto == NULL ? date('Y-m-d h:i:s') : $upto;
        $requestYear = 2017;
        $CTY = $self->dbTable->select('t2w_transactions', 'IFNULL(SUM(amount), 0) as val')
                            ->whereBetween('time_altered', "$requestYear-01-01", $upto, 'ss')
                            ->where('status', 'successful', 's')
                            ->where('ledger', 'credit', 's')
                            ->where('account', $ac_number, 's')
                            ->execute()->row();
        $DTY = $self->dbTable->select('t2w_transactions', 'IFNULL(SUM(amount), 0) as val')
                            ->whereBetween('time_altered', "$requestYear-01-01", $upto, 'ss')
                            ->where('status', 'successful', 's')
                            ->where('ledger', 'debit', 's')
                            ->where('account', $ac_number, 's')
                            ->execute()->row();
        $credits = $CTY !== NULL ? $CTY->val : 0;
        $debits = $DTY !== NULL ? $DTY->val : 0;
        return ["credits" => $credits, "debits" => $debits];
    }

    public static function getCount($ac_number, $upto = NULL) {
        $self = new self;
        $upto = $upto == NULL ? date('Y-m-d h:i:s') : $upto;
        $CTY = $self->dbTable->select('t2w_transactions', 'COUNT(id) as val')
                            ->whereBetween('time_altered', "2018-01-01", $upto, 'ss')
                            ->where('status', 'successful', 's')
                            ->where('ledger', 'credit', 's')
                            ->where('account', $ac_number, 's')
                            ->execute()->row();
        $DTY = $self->dbTable->select('t2w_transactions', 'COUNT(id) as val')
                            ->whereBetween('time_altered', "2018-01-01", $upto, 'ss')
                            ->where('status', 'successful', 's')
                            ->where('ledger', 'debit', 's')
                            ->where('account', $ac_number, 's')
                            ->execute()->row();
        $credits = $CTY !== NULL ? $CTY->val : 0;
        $debits = $DTY !== NULL ? $DTY->val : 0;
        return ["credits" => $credits, "debits" => $debits];
    }

    public static function getBalance($ac_number, $upto = NULL) {
        $self = new self;
        extract($self::getBreakdown($ac_number, $upto));
        return $credits - $debits;
    }

    public static function get($params) {
        extract($params);
        $self = new self;
        return $self->dbTable->joinUserAt('user_id', 'surname', 'other_names', 'middle_name', 'profile_picture', 'gender')
        ->query("$self->statement
            WHERE t1.ac_number LIKE ?
            LIMIT ?
            OFFSET ?
        ", "sii", $ac_number, $limit, $offset)->execute()->rows();
    }

    public static function getSingle($params) {
        extract($params);
        $self = new self;
        return $self->dbTable->joinUserAt('user_id', 'surname', 'other_names', 'middle_name', 'profile_picture', 'gender')
            ->query("$self->statement
            WHERE t1.user_id LIKE ? AND t1.ac_type LIKE ?
        ", "is", $user_id, $ac_type)->execute()->row();
    }

    public static function getByUser($user_id) {
        $self = new self;
        return $self->dbTable->joinUserAt('user_id', 'surname', 'other_names', 'middle_name', 'profile_picture', 'gender')
            ->query("$self->statement
            WHERE t1.user_id LIKE ?
        ", "i", $user_id)->execute()->rows();
    }

    public static function getByNumber($params) {
        extract($params);
        $self = new self;
        return $self->dbTable->joinUserAt('user_id', 'surname', 'other_names', 'middle_name', 'profile_picture', 'gender')
        ->query("$self->statement
            WHERE t1.ac_number LIKE ?
        ", "s", $ac_number)->execute()->row();
    }

    public static function getById($id) {
        $self = new self;
        return $self->dbTable->joinUserAt('user_id', 'surname', 'other_names', 'middle_name', 'profile_picture', 'gender')
        ->query("$self->statement
            WHERE t1.id = ?
        ", "i", (int) $id)->execute()->row();
    }

    public static function editStatus($id, $status) {
        $self = new self;
        $session = Session::getInstance();
        $self->dbTable->update("t2w_accounts")
            ->set("last_altered_by", $session->getResourceOwner()->user_id)
            ->set("status", $status)
            ->where("id", (int) $id)->execute();   

        $account = self::getById($id);

        $self->log("Updated $account->ac_type status for: ".Operations::getFullname($account)." to $status");

        return $account;
    }
}

?>