<?php

namespace KVStore;

class Router
{
    private static $singleton;
    private $routes = [
        "get" => [],
        "post" => [],
        "put" => [],
        "delete" => [],
    ];

    public static function get($route, $handler)
    {
        $router = self::getSingleton();

        $router->routes["get"][$route] = $handler;
    }

    public static function post($route, $handler)
    {
        $router = self::getSingleton();

        $router->routes["post"][$route] = $handler;
    }

    public static function put($route, $handler)
    {
        $router = self::getSingleton();

        $router->routes["put"][$route] = $handler;
    }

    public static function delete($route, $handler)
    {
        $router = self::getSingleton();

        $router->routes["delete"][$route] = $handler;
    }

    public static function run()
    {
        $router = self::getSingleton();
        $request_uri = self::getRequestURI();
        $method = self::getRequestMethod();

        if (!isset($router->routes[$method])) {
            throw new \Exception("Unsupported method: " . $method);
        }

        $routes = $router->routes[$method];

        foreach ($routes as $route => $handler) {
            $match = self::checkRouteMatch($route, $request_uri);

            if ($match !== false) {
                if (is_array($handler)) {
                    $obj = new $handler[0];
                    $response = call_user_func_array([$obj, $handler[1]], $match);
                } else if (is_numeric($handler)) {
                    $response = new Response($handler);
                } else {
                    $response = call_user_func_array($handler, $match);
                }

                if (is_numeric($response)) {
                    $response = new Response($response);
                }

                if ($response instanceof Response) {
                    $response->send();
                }

                return;
            }
        }

        throw new \Exception("Not Found");
    }

    /**
     * @return self
     */
    private static function getSingleton()
    {
        if (!self::$singleton) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }

    private static function getRequestURI()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            throw new \Exception("[Router] REQUEST_URI needs to be set to route");
        }

        $uri = $_SERVER['REQUEST_URI'];
        $qi = strpos($uri, "?");

        if ($qi === false) {
            return $uri;
        }

        return substr($uri, 0, $qi);
    }

    private static function getSplitRequestURI()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            throw new \Exception("[Router] REQUEST_URI needs to be set to route");
        }

        $uri = substr($_SERVER['REQUEST_URI'], 1);

        return explode("/", $uri);
    }

    private static function getRequestMethod()
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @param string $route
     * @param string $path
     */
    private static function checkRouteMatch($route, $path)
    {
        if ($route[0] !== "/") {
            throw new \Exception("[Router] Route must start with /; got: " . $route);
        }
        if ($path[0] !== "/") {
            throw new \Exception("[Router] Path must start with /; got: " . $path);
        }

        // If the path ends in '/' then it can match even if the route doesn't end in a '/'
        if (substr($route, -1) !== "/" && substr($path, -1) === "/") {
            $path = substr($path, 0, -1);
        }

        $route_parts = explode("/", substr($route, 1));
        $path_parts = explode("/", substr($path, 1));

        // We only supprt exact matches
        if (count($route_parts) !== count($path_parts)) {
            return false;
        }

        $data = [];

        for ($i = 0; $i < count($route_parts); $i++) {
            $route_i = $route_parts[$i];
            $path_i = $path_parts[$i];

            if (preg_match("/^{([a-z]+)}$/i", $route_i, $matches)) {
                // placeholder
                $data[$matches[1]] = $path_i;
            } else if ($route_i !== $path_i) {
                // not a placeholder, and doesn't match exactly
                return false;
            }
        }

        return $data;
    }
}
