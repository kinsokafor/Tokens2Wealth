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
        $upto = $upto == NULL ? date('Y-m-d h:i:s') : $upto;
        echo $ac_number;
    }
}

?>