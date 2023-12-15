<?php  

use Public\Modules\<pluginName>\<pluginPrefix>Controller;
use EvoPhp\Api\Requests\Requests;

//API End points

//Pages

$router->get('/<entryURI>', function($params){
    $controller = new BHController;
    $controller->{'<entry>/index'}($params)->auth(2,3,4)->setData(["pageTitle" => "Admin"]);
}); 