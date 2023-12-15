<?php  

use Public\Modules\Tokens2Wealth\T2WController;
use EvoPhp\Api\Requests\Requests;

//API End points

//Pages

$router->get('/t2w', function($params){
    $controller = new T2WController;
    $controller->{'T2WMain/index'}($params)->auth(2,3,4)->setData(["pageTitle" => "Admin"]);
}); 