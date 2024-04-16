<?php  

use Public\Modules\Tokens2Wealth\T2WController;
use EvoPhp\Api\Requests\Requests;
use Public\Modules\Tokens2Wealth\Classes\Migrate;

//API End points
$router->group('/t2w/api', function () use ($router) {
    $router->post('/balance/{ac_number}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBalance($params['ac_number'], $params['date'] ?? NULL);
        });
    });
    $router->post('/break-down/{ac_number}', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBreakdown($params['ac_number'], $params['date'] ?? NULL);
        });
    });
    $router->post('/break-down/count/{ac_number}', function($params){
        $request = new Requests;
        $request->evoAction()->auth(1,2,3)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getCount($params['ac_number']);
        });
    });
    $router->post('/get/accounts', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3)->execute(function() use ($params){
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
        $request->evoAction()->auth(1,2,3)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::editStatus($params['id'], $params['status']);
        });
    });

    // Thrift
    $router->post('/edit-thrift-amount', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3)->execute(function() use ($params){
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

    // term deposit
    $router->post('/new-term-deposit', function($params){
        $request = new Requests;
        $params = array_merge($params, (array) json_decode(file_get_contents('php://input'), true));
        $request->evoAction()->auth(1,2,3,4,5,6,7,8,9)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Contribution::uplines($params["account"]);
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
    $controller->{'T2WAdmin/index'}($params)->auth(4,11)->setData(["pageTitle" => "Admin"]);
}); 

$router->get('/t2w/m', function($params){
    $controller = new T2WController;
    $controller->{'T2WMembers/index'}($params)->auth(6,7,8)->setData(["pageTitle" => "Members"]);
});