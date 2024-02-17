<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Resources\DbTable;
use EvoPhp\Database\Session;

final class Accounts
{
    public $dbTable;

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

        $statement = "CREATE TABLE IF NOT EXISTS t2w_trial_balance (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ac_number VARCHAR(30) NOT NULL,
            `ac_type` VARCHAR(30) NOT NULL,
            `credits` FLOAT(30,2) NOT NULL,
            `debits` FLOAT(30,2) NOT NULL,
            `from_date` TIMESTAMP NOT NULL,
            `to_date` TIMESTAMP NOT NULL,
            `meta` JSON NOT NULL
            )";
        $self->dbTable->query($statement)->execute();
    }

    public static function getBalance($ac_number, $upto = NULL) {
        $self = new self;
        $upto = $upto == NULL ? date('Y-m-d h:i:s') : $upto;
        $requestYear = date("Y", strtotime($upto));
        $CTY = $self->dbTable->select('t2w_transactions', 'SUM(amount) as val')
                            ->whereBetween('time_altered', "$requestYear-01-01", $upto, 'ss')
                            ->where('status', 'successful', 's')
                            ->where('ledger', 'credit', 's')
                            ->where('account', $ac_number, 's')
                            ->execute()->row();
        $DTY = $self->dbTable->select('t2w_transactions', 'SUM(amount) as val')
                            ->whereBetween('time_altered', "$requestYear-01-01", $upto, 'ss')
                            ->where('status', 'successful', 's')
                            ->where('ledger', 'debit', 's')
                            ->where('account', $ac_number, 's')
                            ->execute()->row();
        $BF = $self->dbTable->select('t2w_trial_balance', 'SUM(credits) as credits, SUM(debits) as debits')
                            ->where('ac_number', $ac_number, 's')
                            ->whereBetween('from_date', "2018-01-01", "$requestYear-01-02", 'ss')
                            ->whereBetween('to_date', "2018-01-01", "$requestYear-01-02", 'ss')
                            ->execute()->row();
        $credits = $CTY !== NULL ? $CTY->val : 0;
        $debits = $DTY !== NULL ? $DTY->val : 0;
        if($BF !== NULL) {
            $credits += $BF->credits ?? 0;
            $debits += $BF->debits ?? 0;
        }
        return $credits - $debits;
    }
}

?>