<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Query;
use EvoPhp\Resources\User;
use EvoPhp\Resources\Post;
use EvoPhp\Api\Cron;

final class Migrate
{
    function __construct() 
    {
        
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
        \EvoPhp\Resources\Options::maintainTable();
        \EvoPhp\Resources\Post::maintainTable();
        \EvoPhp\Resources\User::maintainTable();
        \EvoPhp\Resources\Records::maintainTable();
        \EvoPhp\Actions\Action::maintainTable();
        \EvoPhp\Actions\Notifications\Log::maintainTable();
        \EvoPhp\Api\Cron::createTable();
        Cron::schedule('*/5 * * * *', '\Public\Modules\Tokens2Wealth\Classes\Migrate::transactions');
        (new \EvoPhp\Resources\Options)->delete("require_guarantor");
    }

    static function dropTable($tableName) {
        $query = new Query;
        $query->query("DROP TABLE $tableName")->execute();
    }

    static function transactions($cron_id, $args = []) {
        // $query = new Query;
        // $users = $query->select('users', 'id')->execute()->rows();
        // foreach($users as $user) {
        //     (new User)->update((int) $user->id, ['password' => '123456789']);
        // }
        // Cron::cancel($cron_id);
    }

    static function accountsToStore() {
        $posts = new Post;
        print_r($posts->get('credit,debit')->execute());
    }
}


?>