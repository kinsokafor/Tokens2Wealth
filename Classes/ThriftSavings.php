<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;

final class ThriftSavings extends Accounts
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function editThriftAmount($id, $amount) {
        $self = new self;
        $session = Session::getInstance();
        $self->dbTable->update("t2w_accounts")
            ->set("last_altered_by", $session->getResourceOwner()->user_id);
        if($amount <= 0) {
            $amount = 0;
            $self->dbTable->set("status", "inactive");
        } else {
            $self->dbTable->set("status", "active");
        }
        $self->dbTable->metaSet(["amount" => $amount, "last_updated" => time()], [], $id, "t2w_accounts")
            ->where("id", (int) $id)->execute();

        $account = self::getById($id);

        $self->log("Updated thrift savings amount for: ".Operations::getFullname($account));

        return $account;
    }

    public static function nextSettlementDate() {
        $now = time();
        $rtsd = Options::get('rt_settlement');
        return date("Y-m-$rtsd 00:00:00", $now);
    }

    public static function new($params) {
        $session = Session::getInstance();
        if(!$session->getResourceOwner()) return null;
        $user_id = $session->getResourceOwner()->user_id;
        extract($params);
        $self = new self;
        $account = $self::createAccount(
            $user_id,
            "regular_thrift",
            true
        );
        $self->dbTable->update('t2w_accounts')
                        ->set('status', 'active')
                        ->metaSet([
                            "amount" => (double) $params['amount'],
                            "last_updated" => time()
                        ], [], $account->id, "t2w_accounts")
                        ->where('id', $account->id)->execute();
        return Accounts::getById($account->id);
    }
}

?>