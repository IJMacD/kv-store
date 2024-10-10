<?php

namespace KVStore\Models;

use Exception;
use KVStore\AuthException;
use KVStore\Database;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use KVStore\Request;
use UnexpectedValueException;

class Auth
{
    public static function checkBucketAuth(Bucket $bucket, string $access, string $object_key = ""): bool
    {
        // Check if bucket is public first

        if ($access === "read" && $bucket->getReadKey() === null) {
            return true;
        }

        if ($access === "write" && $bucket->getWriteKey() === null) {
            return true;
        }

        if ($access === "admin" && $bucket->getAdminKey() === null) {
            return true;
        }

        // If it's not public, then we need to see if the user provided an
        // access token via any of the permitted methods.
        $token = self::getRequestToken();

        // If the token is actually a JWT then perform custom verification.
        if (self::isJWT($token)) {
            return self::verifyJWT($bucket, $object_key, $access, $token);
        }

        // Otherwise, verify the key against the hashed values in the database.
        return self::verifyKey($bucket, $access, $token);
    }

    public static function checkGodAuth()
    {
        $god_key = $_ENV['KV_GOD_KEY'];

        if (function_exists("apache_request_headers")) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization']) && preg_match("/^Bearer ([a-z0-9\/_+-]+)/", $headers['Authorization'], $matches)) {
                if ($matches[1] === $god_key) {
                    return;
                }
            }
        }

        if (isset($_GET['GOD_KEY_KEY']) && $_GET['GOD_KEY'] === $god_key) {
            return;
        }

        header("HTTP/1.1 403 Forbidden");
        throw new AuthException("Forbidden");
    }

    public static function createBucketToken(Bucket $bucket, array $permissions, string $prefix = "", int $ttl = 86400 * 7 * 52): string
    {
        $secret_key = $bucket->getSecretKey();

        $request = new Request();

        $payload = [
            'iss' => 'http://' . $request->getHost(),
            'aud' => 'http://' . $request->getHost(),
            'iat' => time(),
            'exp' => time() + $ttl,
            'bucket' => $bucket->name,
            'prefix' => $prefix,
            'permissions' => $permissions,
        ];

        return JWT::encode($payload, $secret_key, 'HS256');
    }

    private static function getRequestToken(): string
    {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return $_SERVER['PHP_AUTH_USER'];
        }

        if (function_exists("apache_request_headers")) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization']) && preg_match("/^Bearer ([a-z0-9\/_+-]+)/", $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }

        if (isset($_GET['API_KEY'])) {
            return $_GET['API_KEY'];
        }

        if (isset($_GET['access_key'])) {
            return $_GET['access_key'];
        }

        return "";
    }

    private static function isJWT(string $token): bool
    {
        $dot_pos = strpos($token, ".");

        if ($dot_pos === false) {
            return false;
        }

        try {
            $header = json_decode(substr($token, 0, $dot_pos));

            return $header->typ === "JWT";
        } catch (Exception $e) {
            return false;
        }
    }

    private static function verifyJWT(Bucket $bucket, string $object_key, string $access, string $token): bool
    {
        $secret_key = $bucket->getSecretKey();

        try {
            $decoded = JWT::decode($token, new Key($secret_key, "HS256"));

            return str_starts_with($object_key, $decoded->prefix) && in_array($access, $decoded->permissions);
        } catch (UnexpectedValueException $e) {
            return false;
        }
    }

    private static function verifyKey(Bucket $bucket, string $access, string $key): bool
    {
        if ($access === "read") {
            return password_verify($key, $bucket->getReadKey());
        }

        if ($access === "write") {
            return password_verify($key, $bucket->getWriteKey());
        }

        if ($access === "admin") {
            return password_verify($key, $bucket->getAdminKey());
        }

        return false;
    }
}
