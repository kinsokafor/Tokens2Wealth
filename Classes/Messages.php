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

    public static function approvedTermDeposit($amount, $mv, $md, $userId) {
        $config = new Config;
        $message = "<p>Your new term deposit of NGN $amount is approved by the admin.
                    This deposit is expected to mature on ".date('Y-m-d', $md)." 
                    and an estimated maturity value of NGN $mv";
        $not = new Notifications($message, "TERM DEPOSIT APPROVED - [$config->site_name]");
        $not->to($userId)->template()->mail()->log();
    }

    public static function termDepositLiquidation($fullname, $amount, $premature, $withInterest, $interest_earned, $intent) {
        $config = new Config;
        $message = "<p>$fullname's term deposit of NGN $amount was liquidated successfully.</p>";
        if(!$premature || $withInterest) {
            $message .= "<p>Total interest earned over the period is NGN $interest_earned and interest was paid together with the principal amount.</p>";
        } else {
            $message .= "<p>However, the deposit was liquidated prematurely and so, no interest was paid to the member.</p>";
        }
        $intent = strtoupper($intent);
        $not = new Notifications($message, "TERM DEPOSIT $intent - [$config->site_name]");
        $not->toRole('super_admin', 'admin')->template()->mail()->log();
    }

    public static function guarantorNomination($userId) {
        $config = new Config;
        $message = "A cooperator nominated you to surety his/her loan request. 
        Please login to your portal to review, approve or decline the request. 
        You are also advised not to surety a participant who is not well known to you.";
        $not = new Notifications($message, "REQUEST TO SURETY A LOAN - [$config->site_name]");
        $not->to($userId)->template()->mail()->log();
    }
}

?>