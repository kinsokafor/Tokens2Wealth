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
        $not->to($userId)->template()->mail("sendmail")->log();
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
        $message = "<p>A cooperator nominated you to surety his/her loan request. 
        Please login to your portal to review, approve or decline the request. 
        You are also advised not to surety a participant who is not well known to you.</p>";
        $not = new Notifications($message, "REQUEST TO SURETY A LOAN - [$config->site_name]");
        $not->to($userId)->template()->mail()->log();
    }

    public static function guarantorsAccepted($meta) {
        $config = new Config;
        $fullname = \EvoPhp\Api\Operations::getFullname($meta->user_id);
        $message = "<p>Admin attention is needed. 
            A cooperator '$fullname' just received 2 (two) guarantors approval for a loan request. 
            Please login for final review and possible approval.</p>";
        $not = new Notifications($message, "LOAN REQUEST - [$config->site_name]");
        $not->toRole("super_admin")->template()->mail()->log();
    }

    public static function guarantorsDeclined($meta) {
        $config = new Config;
        $message = "<p>Oops, one of your nominated guarantors declined your request. 
                    Login to the portal to see who.</p>";
        $not = new Notifications($message, "GAURANTOR DECLINED YOUR APPLICATION - [$config->site_name]");
        $not->to($meta->user_id)->template()->mail("sendmail")->log();
    }

    public static function newLoan($meta) {
        $config = new Config;
        $fullname = \EvoPhp\Api\Operations::getFullname($meta->user_id);
        $message = "<p>Admin attention is needed. 
            A cooperator '$fullname' applied for a loan on amount that require no guarantor. 
            Please login for final review and possible approval.</p>";
        $not = new Notifications($message, "LOAN REQUEST - [$config->site_name]");
        $not->toRole("super_admin")->template()->mail()->log();
    }

    public static function loanApproved($meta) {
        $config = new Config;
        $fullname = \EvoPhp\Api\Operations::getFullname($meta->user_id);
        $message = "<p>Congratulations, the admin has just done the final 
            verification of your loan request and approved. 
            Your e-wallet has been credited to that effect. 
            You can then request payout.</p> 
            <p>Details of activities concerning this loan and its repayment 
            can be viewed on your dashboard inside the loan page.</p> 
            <p>Thanks for your cooperation.</p>";
        $not = new Notifications($message, "LOAN REQUEST APPROVED - [$config->site_name]");
        $not->to($meta->user_id)->template()->mail("sendmail")->log();
        $message = "<p>An admin has just authorized a new loan of ".number_format($meta->amount, 2)." for $fullname.</p> 
        <p>Check your dashboard for complete details.</p>";
        $not = new Notifications($message, "LOAN REQUEST APPROVED - [$config->site_name]");
        $not->toRole("super_admin")->template()->log();
    }

    public static function loanDeclined($meta) {
        $config = new Config;
        $message = "<p>Sorry, your requested loan was not approved by the admin.</p> 
            <p>Thanks for your cooperation.</p>";
        $not = new Notifications($message, "LOAN REQUEST DECLINED - [$config->site_name]");
        $not->to($meta->user_id)->template()->mail("sendmail")->log();
    }

    public static function newUser($meta) {
        $config = new Config;
        $link = "$config->root/t2w/activate/$meta->id/$meta->activation";
        $message = "<p>Welcome to $config->site_name. Your new account was registered but not yet active</p>";
        $message .= "<p>To activate your account click the following button<br/></p>"; 
        $message .= "<div><a href=\"$link\" style=\"background: green; color: #fff; padding: 7px; border-radius: 3px;\">ACTIVATE</a></div>";
        $not = new Notifications($message, "LOAN REQUEST DECLINED - [$config->site_name]");
        $not->to($meta->user_id)->template()->mail()->log();
    }

    public static function buyShare($units) {
        $config = new Config;
        $message = "<p>A member just requested to buy $units units of share. Please login to review and approve or decline.</p>";
        $not = new Notifications($message, "SHARES: BUYING - [$config->site_name]");
        $not->toRole("super_admin")->template()->mail()->log();
    }

    public static function sellShare($units) {
        $config = new Config;
        $message = "<p>A member just requested to sell $units units of share. Please login to review and approve or decline.</p>";
        $not = new Notifications($message, "SHARES: SELLING - [$config->site_name]");
        $not->toRole("super_admin")->template()->mail()->log();
    }

    public static function approveBuy($userId, $units) {
        $config = new Config;
        $message = "<p>Your request to buy $units units of shares has been authorized. 
        Thank you for investing in our shares. You are now a shareholder in the cooperative
         and can now earn dividends on your shares.</p>";
        $not = new Notifications($message, "SHARES: BUY - [$config->site_name]");
        $not->to($userId)->template()->mail()->log();
    }

    public static function declineBuy($userId, $units) {
        $config = new Config;
        $message = "<p>Your request to buy $units units of shares was declined.</p>";
        $not = new Notifications($message, "SHARES: BUYING DECLINED - [$config->site_name]");
        $not->to($userId)->template()->mail()->log();
    }

    public static function declineSell($userId, $units) {
        $config = new Config;
        $message = "<p>Your request to sell $units units of shares was declined.</p>";
        $not = new Notifications($message, "SHARES: SELLING DECLINED - [$config->site_name]");
        $not->to($userId)->template()->mail()->log();
    }
}

?>