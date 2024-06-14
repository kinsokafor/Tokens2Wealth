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
                `ledger` VARCHAR(30) NOT NULL DEFAULT 'credit',
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

    public static function postInflow($data) {
        extract($data);
        $self = new self;
        $session = Session::getInstance();
        $id = $self->dbTable->insert("t2w_inflow_outflow", "dssssssi", [
                "amount" => (double) $amount,
                "ledger" => "credit",
                "pop" => $pop ?? "#",
                "narration" => $narration ?? "",
                "classification" => $classification ?? "Not classified",
                "status" => "unconfirmed",
                "meta" => json_encode([
                    "mode_of_payment" => $mode_of_payment ?? "",
                    "date_of_payment" => $date_of_payment ?? ""
                ]),
                "last_altered_by" => $session->getResourceOwner()->user_id
            ])->execute();
        $self->log("Posted an inflow of $amount narrated as $narration and classified as $classification");
        Messages::inflow($amount, $narration);
        return $self->dbTable->select("t2w_inflow_outflow")->where("id", $id)->execute()->row();
    }

    public static function postOutflow($data) {
        extract($data);
        $self = new self;
        $session = Session::getInstance();
        $id = $self->dbTable->insert("t2w_inflow_outflow", "dssssssi", [
                "amount" => (double) $amount,
                "ledger" => "debit",
                "pop" => $pop ?? "#",
                "narration" => $narration ?? "",
                "classification" => $classification ?? "Not classified",
                "status" => "unconfirmed",
                "meta" => json_encode([
                    "mode_of_payment" => $mode_of_payment ?? "",
                    "date_of_payment" => $date_of_payment ?? ""
                ]),
                "last_altered_by" => $session->getResourceOwner()->user_id
            ])->execute();
        $self->log("Posted an inflow of $amount narrated as $narration and classified as $classification");
        Messages::outflow($amount, $narration);
        return $self->dbTable->select("t2w_inflow_outflow")->where("id", $id)->execute()->row();
    }

    public static function balance($from = NULL, $to = NULL) {
        $breakDown = (object) self::breakDown($from, $to);
        return $breakDown->inflow - $breakDown->outflow;
    }

    public static function breakDown($from = NULL, $to = NULL) {
        $self = new self;
        $q = $self->dbTable->select("t2w_inflow_outflow", "SUM(amount) as sum")
            ->where("ledger", "credit")
            ->where("status", "confirmed");
            if($from !== NULL) {
                $q->where("time_altered", $from, "s", ">=");
            }
            if($to !== NULL) {
                $q->where("time_altered", $to, "s", "<=");
            }
        $credits = $q->execute()->row()->sum;

        $q = $self->dbTable->select("t2w_inflow_outflow", "SUM(amount) as sum")
            ->where("ledger", "debit")
            ->where("status", "confirmed");
            if($from !== NULL) {
                $q->where("time_altered", $from, "s", ">=");
            }
            if($to !== NULL) {
                $q->where("time_altered", $to, "s", "<=");
            }
        $debits = $q->execute()->row()->sum;
        
        return ["inflow" => $credits, "outflow" => $debits];
    }

    public static function confirm($id) {
        $self = new self;
        $session = Session::getInstance();
        $data = $self->dbTable->select("t2w_inflow_outflow")->where("id", $id)->execute()->row();
        if($data->status != "unconfirmed") {
            http_response_code(400);
            return "Payment has already been confirmed or declined";
        }
        if($data->amount <= 0) {
            http_response_code(400);
            return "Invalid amount";
        }
        $data = array_merge((array) $data, ["status" => "successful"]);

        $self->dbTable->update("t2w_inflow_outflow")
            ->set("status", "confirmed")
            ->metaSet(["approved_by" => $session->getResourceOwner()->user_id], [], (int) $id)
            ->where("id", $id)
            ->execute();
        return $self->dbTable->select("t2w_inflow_outflow")->where("id", $id)->execute()->row();
    }

    public static function decline($id) {
        $self = new self;
        $session = Session::getInstance();
        $data = $self->dbTable->select("t2w_inflow_outflow")->where("id", $id)->execute()->row();
        if($data->status != "unconfirmed") {
            http_response_code(400);
            return "Payment has already been confirmed or declined";
        }
        $self->dbTable->update("t2w_inflow_outflow")
            ->set("status", "declined")
            ->metaSet(["approved_by", $session->getResourceOwner()->user_id])
            ->where("id", $id)
            ->execute();
        return $self->dbTable->select("t2w_inflow_outflow")->where("id", $id)->execute()->row();
    }
}