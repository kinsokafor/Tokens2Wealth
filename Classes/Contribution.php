<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;

final class Contribution extends Accounts
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function createTable() {
        $self = new self;

        $statement = "CREATE TABLE IF NOT EXISTS t2w_ewallet_transactions (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `amount` FLOAT(30,2) NOT NULL,
                `ledger` VARCHAR(30) NOT NULL DEFAULT 'credit',
                `account` VARCHAR(30) NOT NULL,
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

    public static function downlines($params) {
        extract($params);
        $self = new self;
        return $self->dbTable->joinUserAt('user_id', 'surname', 
        'other_names', 'middle_name', 'profile_picture', 'gender')
            ->query("$self->statement
            WHERE JSON_UNQUOTE(JSON_EXTRACT(t1.meta, '$.\"upline-level$level\"')) LIKE ?
        ", "s", $account)->execute()->rows();
    }

    public static function uplines($account) {
        $self = new self;
        $data = $self::getByNumber(["ac_number" => $account]);
        if($data == null) return [];
        $meta = (array) json_decode($data->meta);
        return $self->dbTable->joinUserAt('user_id', 'surname', 
        'other_names', 'middle_name', 'profile_picture', 'gender')
            ->query("$self->statement
            WHERE ac_number LIKE ?
            OR ac_number LIKE ?
            OR ac_number LIKE ?
            OR ac_number LIKE ?
            OR ac_number LIKE ?
        ", "sssss", 
        $meta["upline-level1"] ?? '', 
        $meta["upline-level2"] ?? '', 
        $meta["upline-level3"] ?? '', 
        $meta["upline-level4"] ?? '', 
        $meta["upline-level5"] ?? '')->execute()->rows();
    }
}

?>