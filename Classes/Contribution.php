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