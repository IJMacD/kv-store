<?php

namespace KVStore\Controllers;

use KVStore\Request;
use KVStore\Response;

class BaseController
{
    protected $request;
    protected $response;

    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
    }
}
