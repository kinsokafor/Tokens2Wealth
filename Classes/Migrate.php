<?php

namespace Public\Modules\Tokens2Wealth\Classes;

use EvoPhp\Database\Query;
use EvoPhp\Resources\Options;
use EvoPhp\Resources\Post;

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

    static function accountsToStore() {
        $posts = new Post;
        print_r($posts->get('credit,debit')->execute());
    }
}


?>