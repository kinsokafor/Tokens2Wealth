<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\DbTable;

final class InflowOutflow 
{
    use AdminLog;

    public $dbTable;
    
    public function __construct() {
        $this->dbTable = new DbTable;
    }

    public static function createTable() {
        $self = new self;

        $statement = "CREATE TABLE IF NOT EXISTS t2w_inflow_outflow (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `amount` FLOAT(30,2) NOT NULL,
                `ledger` VARCHAR(30) NOT NULL DEFAULT 'inflow',
                `pop` TEXT NOT NULL,
                `narration` TEXT NOT NULL,
                `classification` VARCHAR(30) NOT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'unconfirmed',
                `meta` JSON NOT NULL,
                time_altered TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_altered_by BIGINT NOT NULL
                )";
        $self->dbTable->query($statement)->execute();
    }
}