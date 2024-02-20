<?php  

use Public\Modules\Tokens2Wealth\T2WController;
use EvoPhp\Api\Requests\Requests;
use Public\Modules\Tokens2Wealth\Classes\Migrate;

//API End points
$router->group('/t2w/api', function () use ($router) {
    $router->post('/balance/{ac_number}', function($params){
        $request = new Requests;
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBalance($params['ac_number']);;
        });
    });
    $router->post('/break-down/{ac_number}', function($params){
        $request = new Requests;
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getBreakdown($params['ac_number']);;
        });
    });
    $router->post('/break-down/count/{ac_number}', function($params){
        $request = new Requests;
        $request->evoAction()->auth(1,2)->execute(function() use ($params){
            return \Public\Modules\Tokens2Wealth\Classes\Accounts::getCount($params['ac_number']);;
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