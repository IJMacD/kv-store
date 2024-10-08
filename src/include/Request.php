<?php

namespace KVStore;

class Request
{
    private $headers;
    private $rawBody;

    private function getMethod()
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public function getRequestParam($name, $default = null)
    {
        if (isset($_REQUEST[$name])) {
            return $_REQUEST[$name];
        }

        return $default;
    }

    public function getQueryParam($name, $default = null)
    {
        if (isset($_GET[$name])) {
            return $_GET[$name];
        }

        return $default;
    }

    private function getContentType()
    {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            return explode(";", $_SERVER['CONTENT_TYPE'])[0];
        }

        return null;
    }

    public function getHeaders()
    {
        if (!$this->headers) {
            $this->headers = apache_request_headers();
        }
        return $this->headers;
    }

    public function getHeader($name, $default = null)
    {
        $headers = $this->getHeaders();

        if (isset($headers[$name])) {
            return $headers[$name];
        }

        return $default;
    }

    public function getBody()
    {
        $method = $this->getMethod();

        if ($method === "get") {
            return null;
        }

        $contentType = $this->getContentType();

        if ($method === "post" && $contentType === "application/x-www-form-urlencoded") {
            return $_POST;
        }

        if (!$this->rawBody) {
            $this->rawBody = file_get_contents("php://input");
        }

        if ($contentType === "application/x-www-form-urlencoded") {
            // cURL likes to send this content type by default
            // First check to see if it actually looks like form data
            if (str_contains($this->rawBody, "=")) {
                parse_str($this->rawBody, $result);
                return $result;
            }

            return $this->rawBody;
        }

        if ($contentType === "application/json") {
            return json_decode($this->rawBody);
        }

        if ($contentType === "text/plain") {
            return $this->rawBody;
        }

        throw new \Exception("[Request] Unsupported body");
    }

    public function isAccepted($type, $explicit = false)
    {
        $accepted_list = $this->getHeader("Accept");

        if (!$accepted_list) {
            return false;
        }

        $accepted = explode(",", $accepted_list);

        foreach ($accepted as $a) {
            $a = trim(explode(";", $a)[0]);

            if ($a === $type) {
                return true;
            }

            if ($a === "*/*" && !$explicit) {
                return true;
            }
        }

        return false;
    }
}
