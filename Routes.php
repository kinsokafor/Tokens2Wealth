<?php  

use Public\Modules\Tokens2Wealth\T2WController;
use EvoPhp\Api\Requests\Requests;
use Public\Modules\Tokens2Wealth\Classes\Migrate;

//API End points
$router->group('/t2w/api', function () use ($router) {
    $router->post('/registration', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth()->execute(function() use ($params){
            $user = new \EvoPhp\Resources\User;
            if(strtolower($params['referral']) == strtolower($params['email'])) {
                http_response_code(400);
                return 'You cannot refer yourself.';
            }
            if(trim($params['referral']) != "") {
                if($user->get((string) $params['referral']) == NULL) {
                    http_response_code(400);
                    return "Your referrer is invalid. Please Contact the admin.";
                }
            }
            $params = array_merge($params, [
                "username" => \Public\Modules\Tokens2Wealth\Classes\Operations::createMembershipId(),
                "status" => "inactive",
                "role" => "pending",
                "temp_role" => "member",
                "activation" => SHA1(rand(9999, 99999))
            ]);
            $request = new Requests;
            $request->user($params)->auth();
            return $request->response;
        });
    });
    
    $router->post('/upload-pop/admin', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            $user = new \EvoPhp\Resources\User;
            $user->update($params['user_id'], [
                "payment_status" => "paid",
                "pop" => $params["pop"],
                "date_of_payment" => $params['date_of_payment'],
                "mode_of_payment" => "admin confirmation"
            ]);
            return $user->get($params['user_id']);
        });
    });

    $router->post('/approve-user', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::approveUser((int) $params['id']);
        });
    });

    //balances
    $router->post('/balance/{ac_number}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBalance($params['ac_number'], $params['date'] ?? NULL);
        });
    });
    $router->post('/break-down/{ac_number}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBreakdown($params['ac_number'], $params['date'] ?? NULL);
        });
    });
    $router->post('/balance/m/{ac_type}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8)->execute(function() use ($params){
            $session = \EvoPhp\Database\Session::getInstance();
            $account = \Public\Modules\Tokens2Wealth\Classes\Accounts::getSingle([
                "user_id" => $session->getResourceOwner()->user_id,
                "ac_type" => $params['ac_type']
            ]);
            if($account == NULL) {
                http_response_code(400);
                return "No ".$params['ac_type']." account";
            }
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBalance($account->ac_number, $params['date'] ?? NULL);
        });
    });
    $router->post('/break-down/m/{ac_type}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8)->execute(function() use ($params){
            $session = \EvoPhp\Database\Session::getInstance();
            $account = \Public\Modules\Tokens2Wealth\Classes\Accounts::getSingle([
                "user_id" => $session->getResourceOwner()->user_id,
                "ac_type" => $params['ac_type']
            ]);
            if($account == NULL) {
                http_response_code(400);
                return "No ".$params['ac_type']." account";
            }
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBreakdown($account->ac_number, $params['date'] ?? NULL);
        });
    });
    $router->post('/break-down/count/{ac_number}', function($params){
        $request = new Requests;
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getCount($params['ac_number']);
        });
    });
    $router->post('/get/accounts', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::get($params);
        });
    });
    $router->post('/get/account/{ac_type}/{user_id}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9,10)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getSingle($params);
        });
    });
    $router->post('/get/user-accounts', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9,10)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getByUser((int) $params['user_id']);
        });
    });
    $router->post('/get/account-number/{ac_number}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9,10)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getByNumber($params);
        });
    });
    $router->post('/account/edit-status', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::editStatus($params['id'], $params['status']);
        });
    });

    // Thrift
    $router->post('/edit-thrift-amount', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\ThriftSavings::editThriftAmount($params['id'], $params['amount']);
        });
    });

    $router->post('/user/edit-thrift-amount', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\ThriftSavings::editThriftAmount($params['id'], $params['amount']);
        });
    });

    $router->post('/thrift/next-settlement-date', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9,10)->execute(function() {
            return \Public\Modules\Tokens2Wealth\Classes\ThriftSavings::nextSettlementDate();
        });
    });

    $router->post('/new-thrift-savings', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\ThriftSavings::new($params);
        });
    });

    //Loan
    $router->post('/get-loan-components', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Loan::loanComponents($params['id']);
        });
    });

    $router->post('/legibility', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() {
            $session = \EvoPhp\Database\Session::getInstance();
            return \Public\Modules\Tokens2Wealth\Classes\Loan::legibility($session->getResourceOwner()->user_id);
        });
    });

    $router->post('/new-loan', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Loan::new($params);
        });
    });

    $router->post('/pending-guaranteed-loans', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() {
            $session = \EvoPhp\Database\Session::getInstance();
            $user = new \EvoPhp\Resources\User();
            $meta = $user->get($session->getResourceOwner()->user_id);
            return \Public\Modules\Tokens2Wealth\Classes\Loan::pendingGuaranteedLoans($meta->username);
        });
    });

    $router->post('/change-guarantor', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Loan::changeGuarantor($params);
        });
    });

    $router->post('/gaurantor-accept', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params) {
            $session = \EvoPhp\Database\Session::getInstance();
            $user = new \EvoPhp\Resources\User();
            $meta = $user->get($session->getResourceOwner()->user_id);
            return \Public\Modules\Tokens2Wealth\Classes\Loan::guarantorAccept($params['id'], $meta->username);
        });
    });

    $router->post('/gaurantor-decline', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params) {
            $session = \EvoPhp\Database\Session::getInstance();
            $user = new \EvoPhp\Resources\User();
            $meta = $user->get($session->getResourceOwner()->user_id);
            return \Public\Modules\Tokens2Wealth\Classes\Loan::guarantorDecline($params['id'], $meta->username);
        });
    });

    $router->post('/approve-loan', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params) {
            return \Public\Modules\Tokens2Wealth\Classes\Loan::approve((int) $params['id']);
        });
    });

    $router->post('/decline-loan', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params) {
            return \Public\Modules\Tokens2Wealth\Classes\Loan::decline((int) $params['id']);
        });
    });

    $router->post('/pending-loan/count', function(){
        $request = new Requests;
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() {
            return \Public\Modules\Tokens2Wealth\Classes\Loan::pendingLoanCount();
        });
    });

    //Contribution
    $router->post('/downlines/{account}/{level}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::downlines($params);
        });
    });

    $router->post('/uplines/{account}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::uplines($params["account"]);
        });
    });

    $router->post('/confirm-ewallet-payment', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::confirmPayment($params["id"]);
        });
    });

    $router->post('/credit-ewallet', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::creditEWallet($params);
        });
    });

    $router->post('/decline-ewallet-payment', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::declinePayment($params["id"]);
        });
    });

    // term deposit
    $router->post('/new-term-deposit', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\TermDeposit::new($params);
        });
    });

    $router->post('/term-deposit/approve/{id}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\TermDeposit::approve((int) $params['id']);
        });
    });

    $router->post('/modify-term-deposit', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\TermDeposit::modify($params);
        });
    });

    $router->post('/term-deposit/decline/{id}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\TermDeposit::liquidate((int) $params['id'], false, 'Declined');
        });
    });
    
    $router->post('/term-deposit/liquidate/{id}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\TermDeposit::liquidate((int) $params['id'], $params['withInterest'] ?? false);
        });
    });

    $router->post('/m/top-up-term-deposit', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\TermDeposit::topUp((float) $params['amount']);
        });
    });

    //shares
    $router->post('/m/available-shares', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8)->execute(function() use ($params){
            $session = \EvoPhp\Database\Session::getInstance();
            $user = new \EvoPhp\Resources\User();
            return \Public\Modules\Tokens2Wealth\Classes\Shares::availableSharesToIndividual($session->getResourceOwner()->user_id);
        });
    });

    $router->post('/shares/buy', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Shares::buy($params);
        });
    });

    $router->post('/shares/sell', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Shares::sell($params);
        });
    });

    $router->post('/shares/approve', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Shares::approve($params["id"]);
        });
    });

    $router->post('/shares/decline', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Shares::decline($params["id"]);
        });
    });
});

//Pages
$router->get('/t2w/migrate', function() {
    Migrate::migrate();
});

$router->get('/t2w', function($params){
    $controller = new T2WController;
    $controller->{'T2WMain/index'}($params)->template("register")->auth()->setData(["pageTitle" => "Public"]);
}); 

$router->get('/t2w/a', function($params){
    $controller = new T2WController;
    $controller->{'T2WAdmin/index'}($params)->auth(2,3,4,11)->setData(["pageTitle" => "Admin"]);
}); 

$router->get('/t2w/m', function($params){
    $controller = new T2WController;
    $controller->{'T2WMembers/index'}($params)->auth(6,7,8)->setData(["pageTitle" => "Members"]);
});

$router->get('/t2w/pending', function($params){
    $controller = new T2WController;
    $controller->{'T2WPending/index'}($params)->auth(10)->setData(["pageTitle" => "Pending Members"]);
});

$router->get('/t2w/activate/{id}/{code}', function($params) {
    $user = new \EvoPhp\Resources\User;
    $meta = $user->get((int) $params['id']);
    if($meta == NULL) {
        die("Link error");
    }
    if(($meta->activation ?? "") != $params['code']) {
        die("Incorrect activation code");
    }
    $user->update($meta->id, [
        "status" => "active"
    ]);
    $config = new \EvoPhp\Api\Config;
    $home = isset($config->links) ? $config->links->home ?? "/accounts" : $config->loginLink;
    header("Location: $home");
});