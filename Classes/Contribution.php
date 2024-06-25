<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Session;
use EvoPhp\Api\Operations;
use EvoPhp\Api\Config;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\User;
use EvoPhp\Resources\Store;

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

    public static function levelContCount($level) {
        $config = new Config;
        $level = (int) $level;
        return pow($config->contributionCount, $level);
    }

    public static function membersInLevel(int $level) {
        $self = new self;
        return $self->dbTable->joinUserAt('user_id', 'surname', 
        'other_names', 'middle_name', 'profile_picture', 'gender')
            ->query("$self->statement
            WHERE JSON_UNQUOTE(JSON_EXTRACT(t1.meta, '$.\"level\"')) LIKE ?
        ", "i", $level)->execute()->rows();
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

    public static function confirmPayment($id) {
        $self = new self;
        $session = Session::getInstance();
        $data = $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
        if($data->status != "unconfirmed") {
            http_response_code(400);
            return "Payment has already been confirmed or declined";
        }
        if($data->amount <= 0) {
            http_response_code(400);
            return "Invalid amount";
        }
        $data = array_merge((array) $data, ["status" => "successful"]);
        $data["meta"] = array_merge(["pd_id" => $id], (array) json_decode($data["meta"]));
        $credit = Wallets::newCredit($data);
        if($credit == NULL) {
            http_response_code(400);
            return "Something went wrong.";
        }
        $self->dbTable->update("t2w_ewallet_transactions")
            ->set("status", "confirmed")
            ->metaSet(["approved_by" => $session->getResourceOwner()->user_id], [], (int) $id)
            ->where("id", $id)
            ->execute();
        return $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
    }

    public static function declinePayment($id) {
        $self = new self;
        $session = Session::getInstance();
        $data = $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
        if($data->status != "unconfirmed") {
            http_response_code(400);
            return "Payment has already been confirmed or declined";
        }
        $self->dbTable->update("t2w_ewallet_transactions")
            ->set("status", "declined")
            ->metaSet(["approved_by", $session->getResourceOwner()->user_id])
            ->where("id", $id)
            ->execute();
        return $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
    }

    public static function creditEWallet($data) {
        extract($data);
        $self = new self;
        $session = Session::getInstance();
        if((float) $amount <= 0) {
            http_response_code(400);
            return "Invalid amount entered";
        }
        $id = $self->dbTable->insert("t2w_ewallet_transactions", "dsssssssi", [
            "amount" => (float) $amount,
            "ledger" => "credit",
            "account" => $account,
            "pop" => $pop,
            "narration" => $narration,
            "classification" => $classification,
            "meta" => json_encode([
                "mode_of_payment" => $mode_of_payment,
                "date_of_payment" => $date_of_payment
            ]),
            "status" => "unconfirmed",
            "last_altered_by" => $session->getResourceOwner()->user_id
        ])->execute();
        return $self->dbTable->select("t2w_ewallet_transactions")->where("id", $id)->execute()->row();
    }

    public static function approveUser($id) {
        ignore_user_abort(true);
        set_time_limit(200);
        $self = new self;
        $user = new User;
        $meta = $user->get($id);
        $gsa = self::getSingle(["ac_type" => "general_system", "user_id" => 0])->ac_number ?? "";
        $account = $self::createAccount($id, 'contribution', true);
        $uplineLevel1 = $self::uplineLevel1($meta->referral ?? "", $gsa);
        if($uplineLevel1 === $gsa){
            $uplineLevel2 = $uplineLevel3 = $uplineLevel4 = $uplineLevel5 = $uplineLevel1;
        } else {
            $uplineLevel2 = self::whoIsUpline1($uplineLevel1, $gsa);
            $uplineLevel3 = self::whoIsUpline1($uplineLevel2, $gsa);
            $uplineLevel4 = self::whoIsUpline1($uplineLevel3, $gsa);
            $uplineLevel5 = self::whoIsUpline1($uplineLevel4, $gsa);
        }
        $config = new Config();
        $d = date_create("now", new \DateTimeZone($config->timezone));
        $user->update($id, ["role" => "member", "date_created" => $d->format("Y:m:d h:i:s")]);
        $self->dbTable->update("t2w_accounts")
            ->metaSet([
                "upline-level1" => $uplineLevel1,
                "upline-level2" => $uplineLevel2,
                "upline-level3" => $uplineLevel3,
                "upline-level4" => $uplineLevel4,
                "upline-level5" => $uplineLevel5,
                "level" => 0
            ], [], $account->id, "t2w_accounts")
            ->where("id", $account->id)->execute();
        $registrationFee = Options::get("t2w_registration_fee") ?? 20000;
        Wallets::creditAccount(
            [
                "narration" => "Being the total registration fee",
                "amount" => $registrationFee
            ], $account->ac_number
        );
        Wallets::debitAccount(
            [
                "narration" => "Being compulsory non-refundable membership fee",
                "amount" => $registrationFee
            ], $account->ac_number
        );
        self::upgrade($account, 2000, $gsa);
        return $user->get($id);
    }

    public static function upgrade($account, $upgradeSum, $gsa = "") {
        ignore_user_abort(true);
        set_time_limit(200);
        $self = new self;
        $nextLevel = (int) ($account->level ?? 0) + 1;
        if($gsa == "") {
            $gsa = self::getSingle(["ac_type" => "general_system", "user_id" => 0])->ac_number ?? "";
        }

        //referral
        if($nextLevel > 5) {
            // end cycle
            return;
        } else {
            $uplineAccount = $self->dbTable
                ->select("t2w_accounts", "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.\"upline-level$nextLevel\"')) as upline")
                ->where("id", $account->id, 'i')->execute()->row()->upline ?? "";
            $upline = $self->dbTable->joinUserAt("user_id", "referral")
                ->select("t2w_accounts")
                ->where("ac_number", $uplineAccount)->execute()->row();
            if($upline == NULL || trim($upline->referral ?? "") == "") {
                $referral = $gsa;
            } else {
                $user = new User;
                $referral_meta = $user->get((string) $upline->referral);
                $ref_account = $self::getSingle(["user_id" => $referral_meta->id, "ac_type" => "contribution"]);
                $referral = $ref_account->ac_number ?? $gsa;
            }
        }

        //upgrade
        $self->dbTable->update("t2w_accounts")
            ->metaSet([
                "level" => $nextLevel
            ], [], $account->id, "t2w_accounts")
            ->where("id", $account->id)->execute();

        //credit referral bonus
        if($nextLevel < 5) {
            $referralBonusRate = Options::get("ref_bonus") ?? 10;
			$referralBonus = $referralBonusRate * 0.01 * $upgradeSum;
			Wallets::creditAccount(
                [
                    "narration" => "Referral bonus from $account->ac_number wrt your referee $uplineAccount",
                    "amount" => $referralBonus
                ], $referral
            );
		}

        $pddwn = self::getPaidDownlines($upline->ac_number, $nextLevel);
        $lcc = self::levelContCount($nextLevel);

        if($nextLevel == 1) 
		{
			//credit upgrade account
			$upgradeBalance = $upline->upgrade_bal ?? 0;
			$upgradeBalance = $upgradeBalance + ($upgradeSum - $referralBonus);
			
            //credit upline's upgrade balance
            $self->dbTable->update("t2w_accounts")
                ->metaSet([
                    'upgrade_bal' => $upgradeBalance
                ], [], $upline->id, "t2w_accounts")
                ->where("id", $upline->id)->execute();

			if(($pddwn >= $lcc) && $upline->ac_number != $gsa) {
				
                //upline has received maximum payment for the level
				$self->dbTable->update("t2w_accounts")
                    ->metaSet([
                        'upgrade_bal' => 0
                    ], [], $upline->id, "t2w_accounts")
                    ->where("id", $upline->id)->execute();

				$self::upgrade($upline, $upgradeBalance, $gsa);
			}
		}
        else if($nextLevel > 1 && $nextLevel < 5) {

			//credit upgrade account
			$uplinePay = ($upgradeSum - $referralBonus)/2;
			$upgradeBalance = $upline->upgrade_bal ?? 0;
			$lienBalance = $upline->lien_bal ?? 0;
            $percentageLien = Options::get("percentage_lien") ?? 10;
			$lien = $uplinePay * $percentageLien * 0.01;
			$lienBalance = $lienBalance + $lien;
			$upgradeBalance = $upgradeBalance + $uplinePay;

            //credit upline's upgrade balance
            $self->dbTable->update("t2w_accounts")
                ->metaSet([
                    'upgrade_bal' => $upgradeBalance,
                    'lien_bal' => $lienBalance
                ], [], $upline->id, "t2w_accounts")
                ->where("id", $upline->id)->execute();

			$uplinePay = $uplinePay - $lien;

            Wallets::creditAccount(
                [
                    "narration" => "Level $nextLevel upgrade from $account->ac_number",
                    "amount" => $uplinePay,
                    "meta" => ["cat" => "contribution"]
                ], $upline->ac_number
            );

			if(($pddwn >= $lcc) && ($upline->ac_number != $gsa)) {
				$self->dbTable->update("t2w_accounts")
                    ->metaSet([
                        'upgrade_bal' => 0
                    ], [], $upline->id, "t2w_accounts")
                    ->where("id", $upline->id)->execute();

				$self::upgrade($upline, $upgradeBalance, $gsa);
			}
		}
        else if($nextLevel == 5) {

			//liquidate lien
            Wallets::creditAccount(
                [
                    "narration" => "Lien account liquidation",
                    "amount" => $account->lien_bal,
                    "meta" => ["cat" => "contribution"]
                ], $upline->ac_number
            );

            $self->dbTable->update("t2w_accounts")
                    ->metaSet([
                        'lien_bal' => 0
                    ], [], $account->id, "t2w_accounts")
                    ->where("id", $account->id)->execute();

			//credit upgrade account
			$uplinePay = $upgradeSum;
            Wallets::creditAccount(
                [
                    "narration" => "Level $nextLevel upgrade from $account->ac_number",
                    "amount" => $uplinePay,
                    "meta" => ["cat" => "contribution"]
                ], $upline->ac_number
            );

			if(($pddwn >= $lcc) && ($upline->ac_number != $gsa)) {
				// upline has received maximum payment for the level
				//cycle out upline
                $self->dbTable->update("t2w_accounts")
                    ->metaSet([
                        'level' => 'Life Member'
                    ], [], $upline->id, "t2w_accounts")
                    ->where("id", $upline->id)->execute();
			}
		}
    }

    public static function uplineLevel1($referral = "", $gsa = "") {
        $self = new self;
        if($referral != '') {
            $user = new User;
            $referral_meta = $user->get((string) $referral);
            $ref_account = $self::getSingle(["user_id" => $referral_meta->id, "ac_type" => "contribution"]);
            if($ref_account !== NULL) {
                return $ref_account->ac_number;
            }
        }
        $contributionCount = $self::levelContCount(1);
        $statement = "
            SELECT id, user_id, ac_number, ac_type, status, meta, 
                time_altered, IFNULL(t2.count, 0) as count FROM t2w_accounts as t1 
            LEFT JOIN
                (
                SELECT COUNT(upline) as count, upline FROM 
                    (
                        SELECT meta, JSON_UNQUOTE(JSON_EXTRACT(meta, '$.\"upline-level1\"')) as upline 
                            FROM t2w_accounts) as t3
                        group by upline
                    ) as t2
            ON t1.ac_number = t2.upline
            WHERE `ac_type` LIKE 'contribution' 
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.meta, '$.level')) LIKE 1 
                AND IFNULL(t2.count, 0) < $contributionCount
            ORDER BY `time_altered` ASC
        ";
        $account = $self->dbTable->query($statement)->execute()->row();
        if($account == NULL) {
            if($gsa == "") {
                return $self::getSingle(["ac_type" => "general_system", "user_id" => 0])->ac_number ?? "";
            } else return $gsa;
        } else return $account->ac_number;
    }

    public static function whoIsUpline1($account, $gsa = "") {
        $self = new self;
        $statement = "SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(meta, '$.\"upline-level1\"')) as upline 
            FROM t2w_accounts WHERE ac_number LIKE ?";
        return $self->dbTable->query($statement, "s", (string) $account)->execute()->row()->upline ?? $gsa;
    }

    public static function getPaidDownlines($ac_number, $level = 1) {
        $self = new self;
        return $self->dbTable->select("t2w_accounts", "COUNT(id) as count")
            ->where("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.\"upline-level$level\"'))", (string) $ac_number, "s")
            ->where("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.level'))", $level, false, ">=")
            ->execute()->row()->count;
    }

    public static function bulkCredit($cronId, $args) {
        $amount = $args[0] ?? 0;
        $narration = $args[1] ?? "";
        $self = new self;
        $accounts = $self->dbTable->select("t2w_accounts")
                        ->where("ac_type", "contribution")
                        ->execute()->rows();
        $membersCount = Operations::count($accounts);
        if($membersCount <= 0) {
            \EvoPhp\Api\Cron::cancel($cronId);
            return;
        }
        $creditAmount = $amount/$membersCount;
        $participating_members = [];
        foreach ($accounts as $account) {
            set_time_limit(60);
            Wallets::creditAccount(
                [
                    "narration" => $narration,
                    "amount" => $creditAmount
                ], $account->ac_number
            );
            array_push($participating_members, $account->user_id);
        }
        $store = new Store;
        $store->new("bulk_credit", [
            "participating_members" => $participating_members,
            "amount" => (double) $amount,
            "narration" => $narration,
            "mode" => "all_ewallets"
        ]);
        $self->log("Super admin Implemented a bulk credit exercise to all e-wallets and $membersCount accounts were credited. 
        Total sum of NGN ".number_format($amount)." was shared. Thank you");
        \EvoPhp\Api\Cron::cancel($cronId);
        return;
    }

    public static function bulkDebit($cronId, $args) {
        $amount = $args[0] ?? 0;
        $narration = $args[1] ?? "";
        $self = new self;
        $accounts = $self->dbTable->select("t2w_accounts")
                        ->where("ac_type", "contribution")
                        ->execute()->rows();
        $membersCount = Operations::count($accounts);
        if($membersCount <= 0) {
            \EvoPhp\Api\Cron::cancel($cronId);
            return;
        }
        $system_account = self::getGeneralSystemAccount();
        $participating_members = [];
        $refDate = date('Y-m-d', time());
        foreach ($accounts as $account) {
            set_time_limit(60);
            if(self::getBalance($account->ac_number) < $amount) {
                PendingDebits::new([
                    "debit_account" => $account->ac_number,
                    "credit_account" => $system_account,
                    "narration" => "Back duty due on $refDate for: $narration",
                    "amount" => $amount,
                    "category" => "bulk_debit",
                ]);
            } else {
                Wallets::debitAccount(
                    [
                        "narration" => $narration,
                        "amount" => $amount
                    ], $account->ac_number
                );

                Wallets::creditAccount(
                    [
                        "narration" => $narration,
                        "amount" => $amount
                    ], $system_account
                );
            }
            array_push($participating_members, $account->user_id);
        }
        $store = new Store;
        $store->new("bulk_debit", [
            "participating_members" => $participating_members,
            "amount" => (double) $amount,
            "narration" => $narration,
            "mode" => "all_ewallets"
        ]);
        $self->log("Super admin Implemented a bulk debit exercise to all e-wallets and $membersCount accounts were debited of NGN ".number_format($amount).". Thank you");
        \EvoPhp\Api\Cron::cancel($cronId);
        return;
    }
}

?>