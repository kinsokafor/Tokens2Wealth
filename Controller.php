<?php 

namespace Public\Modules\<pluginName>;

use EvoPhp\Api\Controllers;
use EvoPhp\Api\Config;

class <pluginPrefix>Controller extends Controllers
{

    public function __construct()
    {
        parent::__construct();
        $this->viewPath = __DIR__.'/Views';
        $this->config = new Config;
    }

    public function getData($data) {
        if(method_exists($this, $this->dataMethod)) {
            $callback = $this->dataMethod;
            return $this->$callback($data);
        }
        return false;
    }

    public function addResources() {
        if(method_exists($this, $this->resourceMethod)) {
            $callback = $this->resourceMethod;
            return $this->$callback();
        }
        return false;
    }
    
}
