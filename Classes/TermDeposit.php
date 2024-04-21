<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Resources\Options;

final class TermDeposit extends Accounts
{
    use AdminLog;
    
    public function __construct() {
        parent::__construct();
    }

    public static function new($params) {
        $session = Session::getInstance();
        $user_id = $session->getResourceOwner()->user_id;
        if(!$session->getResourceOwner()) return null;
        extract($params);
        $self = new self;
        $account = $self::createAccount(
            $user_id,
            "term_deposit",
            true
        );
        $contribution = Accounts::getSingle(['ac_type' => 'contribution', 'user_id' => $user_id]);
        if(Accounts::getBalance($contribution->ac_number) < (double) $amount) {
            http_response_code(400);
            return "Insufficient funds in your E-Wallet";
        } else {
            $d = Wallets::debitAccount([
                "amount" => $amount,
                "narration" => "A new debit from your e-wallet $contribution->ac_number to fund your term deposit $account->ac_number"
            ], $contribution->ac_number);

            if($d == NULL) {
                http_response_code(400);
                return "Failed";
            }

            $c = Wallets::creditAccount([
                "amount" => $amount,
                "narration" => "A new credit from your e-wallet $contribution->ac_number to fund your term deposit $account->ac_number"
            ], $account->ac_number);

            if($c == NULL) {
                $self->dbTable->delete('t2w_transactions')->where('id', $d->id)->execute();
                http_response_code(400);
                return "Failed";
            }
        }
        $td_rate = Options::get("td_rate");
        $self->dbTable->update('t2w_accounts')
                        ->set('status', 'pending')
                        ->metaSet([
                            "td_tenure" => $tenure,
                            "td_rate" => $td_rate
                        ])
                        ->where('id', $account->id)->execute();
        Messages::newTermDeposit($account);              
        return Accounts::getById($account->id);
    }
}

?>