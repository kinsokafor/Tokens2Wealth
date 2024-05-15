<?php

namespace Public\Modules\Tokens2Wealth\Classes;
use EvoPhp\Resources\Store;

trait AdminLog
{
    
    public function log($message) {
        $store = new Store;
        $store->new("admin_log", [
            "narration" => $message
        ]);
    }
}

?>