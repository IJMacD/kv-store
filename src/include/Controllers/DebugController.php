<?php

namespace KVStore\Controllers;

use Firebase\JWT\JWT;

class DebugController extends BaseController
{
    private string $secret_key = "u1O4luVi+DSaMlUJDR2GjGs/AskmZ5icNiPy0hIDGXU=";

    /**
     * GET /debug
     */
    public function debug()
    {
        $payload = [
            'iss' => 'http://' . $this->request->getHost(),
            'aud' => 'http://' . $this->request->getHost(),
            'iat' => time(),
            'exp' => time() + 86400 * 7 * 52,
            'bucket' => "",
            'prefix' => "",
            'permissions' => ["read", "write"]
        ];


        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');

        return $this->response->text($jwt);
    }
}
