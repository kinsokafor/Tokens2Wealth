<?php 

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Actions\Notifications\Notifications;
use EvoPhp\Api\Config;
use EvoPhp\Api\Operations;
use Public\Modules\Tokens2Wealth\Classes\Accounts;

class Messages
{

    public function __construct()
    {
        
    }

    public static function pendingDebit($data) {
        $account = Accounts::getByNumber(['ac_number' => $data->debit_account]);
        $message = "<p>A new pending debit was queued for you</p>";
        $message .= "<ul>
                        <li>Debit Account: <strong>$data->debit_account</strong></li>
                        <li>Credit Account: <strong>$data->credit_account</strong></li>
                        <li>Amount: <strong>$data->amount</strong></li>
                        <li>Date: <strong>$data->time_altered</strong></li>
                    </ul>";
        $message .= "<p>Payment will be deducted automatically, immediately you make payment. To cancel, please contact support.</p>";
        $not = new Notifications($message);
        $not->to($account->user_id)->log();
    }

    public static function newTermDeposit($data) {
        $config = new Config;
        $message = "<p>Your term deposit is being processed. 
                    Admin will get back to you as soon as possible.</p>";
        $not = new Notifications($message);
        $not->to($data->user_id)->log();
        $message = "A new term deposit request requiring admin urgent 
                    attention has just been raised. Kindly login to review, 
                    and approve as soon as you can. Thank you.";
        $not = new Notifications($message, "TERM DEPOSIT REQUEST - [$config->site_name]");
        $not->toRole('super_admin', 'admin')->template()->mail()->log();
    }

}

?>