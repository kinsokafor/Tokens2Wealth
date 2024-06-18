<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Query;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\DbTable;
use EvoPhp\Resources\User;
use EvoPhp\Api\Operations;

final class Migrate
{
    public $query;

    function __construct() 
    {
        $this->query = new Query;
    }

    static function migrate() {
        self::dropTable("cache");
        self::dropTable("contacts");
        self::dropTable("ewallet_snapshot");
        self::dropTable("externals");
        self::dropTable("ledger_snapshot");
        self::dropTable("result_pins");
        self::dropTable("visitors");
        self::dropTable("filters");
        Options::maintainTable();
        \EvoPhp\Resources\Post::maintainTable();
        \EvoPhp\Resources\User::maintainTable();
        \EvoPhp\Resources\Records::maintainTable();
        \EvoPhp\Actions\Action::maintainTable();
        \EvoPhp\Actions\Notifications\Log::maintainTable();
        \EvoPhp\Api\Cron::createTable();
        $options = new Options;
        $options->delete("require_guarantor");
        $options->delete("crd");
        $options->delete("regava");
    }

    static function dropTable($tableName) {
        $query = new Query;
        $query->query("DROP TABLE $tableName")->execute();
    }

    public static function maintainTable() {
        $self = new self;
        $statement = "ALTER TABLE users ADD
                        (
                            meta JSON NOT NULL
                        )";
        $self->query->query($statement)->execute();
    }

    private function get($selector) {
        $user = new User;
        $selectColumn = "user_id";
        $selector = $selector;
        $selectorType = "i";
        
        
        $stmt = "SELECT DISTINCT meta_name, meta_value, username, email, users.id, password, date_created
            FROM user_meta 
            LEFT JOIN users ON users.id = user_meta.user_id
            WHERE user_id IN (SELECT id FROM users WHERE $selectColumn = ?)
            ORDER BY meta_value ASC";

        $res = $this->query->query($stmt, $selectorType, $selector)->execute()->rows("OBJECT_K");
        if(empty($res)) {
            $user->error = "User not found";
            $user->errorCode = 500;
            return false;
        }
        $meta = array_map(function($v){
            $meta_value = htmlspecialchars_decode($v->meta_value);
            return Operations::removeslashes($meta_value); 
        }, $res);
        $meta['email'] = $res['role']->email;
        $meta['username'] = $res['role']->username;
        $meta['password'] = $res['role']->password;
        $meta['date_created'] = $res['role']->date_created;
        $meta['id'] = $res['role']->id;
        $meta = (object) $meta;
        return $meta;
    }

    public static function migrateUsers() {
        echo "Initiating!!!<br/>";
        $self = new self;
        self::maintainTable();
        $dbTable = new DbTable;
        $v = $dbTable->select("users", "id")->where("meta", "null")->limit(20)->execute()->rows();
        if(Operations::count($v)) {
            foreach($v as $vv) {
                $meta = $self->get($vv->id);
                if($meta == false) $meta = [];
                $dbTable->update("users")->metaSet($meta, [
                    "id", "username", "email", "password", "meta", "date_created"
                ])->where("id", $vv->id)->execute();
            }
            echo "Done 20. Please refresh.";
        } else {
            echo "Done!!!";
        }
    }
}


?>