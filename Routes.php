<?php  

use Public\Modules\Tokens2Wealth\T2WController;
use EvoPhp\Api\Requests\Requests;
use Public\Modules\Tokens2Wealth\Classes\Migrate;

//API End points
$router->group('/t2w/api', function () use ($router) {
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
});

//Pages
$router->get('/t2w/migrate', function($params) {
    Migrate::migrate();
});

$router->get('/t2w', function($params){
    $controller = new T2WController;
    $controller->{'T2WMain/index'}($params)->auth()->setData(["pageTitle" => "Public"]);
}); 

$router->get('/t2w/a', function($params){
    $controller = new T2WController;
    $controller->{'T2WAdmin/index'}($params)->auth(2,3,4,11)->setData(["pageTitle" => "Admin"]);
}); 

$router->get('/t2w/m', function($params){
    $controller = new T2WController;
    $controller->{'T2WMembers/index'}($params)->auth(6,7,8)->setData(["pageTitle" => "Members"]);
});