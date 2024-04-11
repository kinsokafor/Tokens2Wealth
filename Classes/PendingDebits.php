<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Resources\DbTable;
use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;

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
        $self = new self;
        $session = Session::getInstance();
        $id = $self->dbTable->insert("t2w_pending_debits", "", [
            "debit_account" => $debit_account,
            "credit_account" => $credit_account,
            "narration" => $narration,
            "amount" => (float) $amount,
            "category" => $category,
            "status" => $status,
            "meta" => json_encode($meta),
            "last_altered_by" => $session->getResourceOwner() ? (int) $session->getResourceOwner()->user_id : 0
        ])->execute();
        return $self->dbTable->select("t2w_pending_debits")->where("id", $id)->execute()->row();
    }
}

?>