<?php

namespace KVStore\Controllers;

class WelcomeController extends BaseController
{
    public function index()
    {
        return $this->response->serveFile("views/index.html");
    }
}
