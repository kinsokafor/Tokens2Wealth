<?php  

use Public\Modules\Tokens2Wealth\T2WController;
use EvoPhp\Api\Requests\Requests;
use Public\Modules\Tokens2Wealth\Classes\Migrate;
use Public\Modules\Tokens2Wealth\Classes\Messages;

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
                "status" => "active",
                "role" => "pending",
                "temp_role" => "member",
                "activation" => SHA1(rand(9999, 99999))
            ]);
            $request = new Requests;
            $request->user($params)->auth();
            Messages::newRegistration();
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

    $router->post('/get-general-system-account', function(){
        $request = new Requests;
        $request->evoAction()->auth(1,2)->execute(function(){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getGeneralSystemAccount();
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
            $params['ac_number'] = rawurldecode($params['ac_number']);
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
    $router->post('/reverse-statement', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Wallets::reverse((int) $params['id']);
        });
    });

    //Inflow Outflow //Deposit Payouts
    $router->post('/balance/inflow-outflow', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\InflowOutflow::balance($params['from'] ?? NULL, $params['to'] ?? NULL);
        });
    });

    $router->post('/break-down/inflow-outflow', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\InflowOutflow::breakDown($params['from'] ?? NULL, $params['to'] ?? NULL);
        });
    });

    $router->post('/balance/deposit-payout', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Payout::balance($params['from'] ?? NULL, $params['to'] ?? NULL);
        });
    });

    $router->post('/break-down/deposit-payout', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Payout::breakDown($params['from'] ?? NULL, $params['to'] ?? NULL);
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

    $router->post('/thrift/liquidate', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9,10)->execute(function() use ($params) {
            return \Public\Modules\Tokens2Wealth\Classes\ThriftSavings::liquidate((int) $params['id']);
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

    $router->post('/approved-guaranteed-loans', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() {
            $session = \EvoPhp\Database\Session::getInstance();
            $user = new \EvoPhp\Resources\User();
            $meta = $user->get($session->getResourceOwner()->user_id);
            return \Public\Modules\Tokens2Wealth\Classes\Loan::approvedGuaranteedLoans($meta->username);
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

    $router->post('/settle-loan', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params) {
            return \Public\Modules\Tokens2Wealth\Classes\Loan::settleBalance($params['ac_number'], (int) $params['user_id']);
        });
    });

    $router->post('/recover-loan', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params) {
            return \Public\Modules\Tokens2Wealth\Classes\Loan::recover($params['ac_number'], (int) $params['user_id']);
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

    //payout
    $router->post('/m/okay-to-request-payout', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8,9)->execute(function() use ($params){
            $session = \EvoPhp\Database\Session::getInstance();
            return \Public\Modules\Tokens2Wealth\Classes\Payout::okToRequest($session->getResourceOwner()->user_id);
        });
    });

    $router->post('/m/request-payout', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Payout::new((float) $params['amount']);
        });
    });

    $router->post('/payout/confirm', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Payout::markAsPaid($params);
        });
    });

    $router->post('/payout/decline', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Payout::decline((int) $params['id']);
        });
    });

    //inflow outflow
    $router->post('/post-inflow', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\InflowOutflow::postInflow($params);
        });
    });

    $router->post('/post-outflow', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\InflowOutflow::postOutflow($params);
        });
    });

    $router->post('/confirm-inflow-outflow', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\InflowOutflow::confirm($params["id"]);
        });
    });

    $router->post('/decline-inflow-outflow', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\InflowOutflow::decline($params["id"]);
        });
    });

    //operations
    $router->post('/m/profile-completeness', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(6,7,8,9)->execute(function() use ($params){
            $session = \EvoPhp\Database\Session::getInstance();
            return \Public\Modules\Tokens2Wealth\Classes\Operations::profileCompleteness($session->getResourceOwner()->user_id);
        });
    });

    $router->post('/bulk-credit', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            switch ($params['mode']) {
                case 'all_ewallets':
                    return \EvoPhp\Api\Cron::schedule(
                        "* * * * *", 
                        "\Public\Modules\Tokens2Wealth\Classes\Contribution::bulkCredit",
                        $params['amount'], $params['narration']);
                    break;

                case 'rt_dividends':
                    return \EvoPhp\Api\Cron::schedule(
                        "* * * * *", 
                        "\Public\Modules\Tokens2Wealth\Classes\ThriftSavings::bulkCredit",
                        $params['amount'], $params['narration']);
                    break;

                case 'share_dividends':
                    return \EvoPhp\Api\Cron::schedule(
                        "* * * * *", 
                        "\Public\Modules\Tokens2Wealth\Classes\Shares::bulkCredit",
                        $params['amount'], $params['narration']);
                    break;
                
                default:
                    http_response_code(401);
                    return "Invalid submission";
                    break;
            }
        });
    });

    $router->post('/bulk-debit', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \EvoPhp\Api\Cron::schedule(
                "* * * * *", 
                "\Public\Modules\Tokens2Wealth\Classes\Contribution::bulkDebit",
                $params['amount'], $params['narration']);
        });
    });

    $router->post('/credit-single', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::creditSingle($params);
        });
    });

    $router->post('/debit-single', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::debitSingle($params);
        });
    });
});

//Pages
$router->get('/t2w/migrate', function() {
    Migrate::migrate();
});

$router->get('/t2w/migrate-users', function() {
    Migrate::migrateUsers();
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