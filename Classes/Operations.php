<?php

namespace Public\Modules\Tokens2Wealth\Classes;

final class Operations
{
    
    public function __construct() {}

    public static function hashing($verb) {
        return SHA1(md5($verb."agdjsjkaHJKKL@#@THShfkKJYIUUKK56%^%^nmmmJKI234".$verb));
    }

    public static function createMembershipId(){
        //at the begining of a new centenary, to avoid duplicate account numbers, 
        // simply count up the default ac_code(s)
        // for system accounts, user_id should be zero
        $time_code = date('ym', time());
        $user =  new \EvoPhp\Resources\User;
        $i = 1;
        do {
            if(strlen($i) < 2) {
                $num_code = '000'.$i;
            }
            else if(strlen($i) < 3) {
                $num_code = '00'.$i;
            }
            else if(strlen($i) < 4) {
                $num_code = '0'.$i;
            }
            else{
                $num_code = $i;
            }
            $membershipId = (string) $time_code.$num_code;
            $i++;
        } while($user->get($membershipId) != NULL);
        return $membershipId;
    }

    public static function afterSignUp($userId) {
        $user =  new \EvoPhp\Resources\User;
        $meta = $user->get((int) $userId);
        Messages::newUser($meta);
    }
}

?>