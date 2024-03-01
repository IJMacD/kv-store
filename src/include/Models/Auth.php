<?php

namespace KVStore\Models;

use KVStore\AuthException;
use KVStore\BucketAuth;
use KVStore\Database;

class Auth
{
    public static function getBucketAuth($bucket_name)
    {
        $bucketAuth = new BucketAuth();

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $result = self::getBucketAuthByType($bucket_name, "basic", $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
            if ($result) {
                self::resolveAuth($bucketAuth, $result);
            }
        }

        if (function_exists("apache_request_headers")) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization']) && preg_match("/^Bearer ([a-z0-9\/_+-]+)/", $headers['Authorization'], $matches)) {
                $result = self::getBucketAuthByType($bucket_name, "bearer", null, $matches[1]);
                if ($result) {
                    self::resolveAuth($bucketAuth, $result);
                }
            }
        }

        if (isset($_GET['API_KEY'])) {
            $result = self::getBucketAuthByType($bucket_name, "bearer", null, $_GET['API_KEY']);
            if ($result) {
                self::resolveAuth($bucketAuth, $result);
            }
        }

        $result = self::getBucketAuthByType($bucket_name, "public");
        if ($result) {
            self::resolveAuth($bucketAuth, $result);
        }

        return $bucketAuth;
    }


    private static function getBucketAuthByType($bucket_name, $type, $identifier = null, $secret = null)
    {
        $db = Database::getSingleton();

        $sql =
            'SELECT
                can_list,
                can_read,
                can_create,
                can_edit,
                can_delete,
                can_admin
            FROM auth
                JOIN buckets USING (bucket_id)
            WHERE
                "bucket_name" = :bucket_name AND "auth_type" = :type';

        $params = ["bucket_name" => $bucket_name, "type" => $type];

        if ($type === "basic") {
            $sql .= ' AND "identifier" = :identifier';
            $params["identifier"] = $identifier;
            $sql = str_replace("SELECT", 'SELECT "secret",', $sql);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();

            if ($result && password_verify($secret, $result['secret'])) {
                unset($result['secret']);
                return $result;
            }

            return false;
        }

        if ($type === "bearer") {
            $sql .= ' AND "secret" = :secret';
            $params["secret"] = $secret;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        }

        if ($type === "public") {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        }

        throw new \Exception("[Database] Unrecognised Auth Type $type");
    }

    private static function checkBucketAuthByTypeExists($bucket_name, $type)
    {
        $db = Database::getSingleton();

        $stmt = $db->prepare(
            'SELECT COUNT(*)
            FROM auth
                JOIN buckets USING (bucket_id)
            WHERE "bucket_name" = :bucket_name AND "auth_type" = :auth_type'
        );
        $stmt->execute(["bucket_name" => $bucket_name, "auth_type" => $type]);

        return $stmt->fetchColumn() > 0;
    }

    public static function checkBucketAuth($bucket_name, $type)
    {
        $bucketAuth = self::getBucketAuth($bucket_name);

        if (!$bucketAuth->{$type}) {
            if (self::checkBucketAuthByTypeExists($bucket_name, "basic")) {
                header("HTTP/1.1 401 Unauthorized");
                header("WWW-Authenticate: Basic realm=\"KV Store\"");
            } else {
                header("HTTP/1.1 403 Forbidden");
            }

            throw new AuthException("Not permitted to $type objects in $bucket_name");
        }
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

    public static function addBucketAuth(string $bucket_name, string $auth_type, BucketAuth $auth_object, $identifier = null, $secret = null)
    {
        $db = Database::getSingleton();

        $bucket_id = Bucket::getBucketID($bucket_name);

        $stmt = $db->prepare(
            'INSERT INTO auth
                ("bucket_id", "auth_type", "identifier", "secret", "can_list", "can_read", "can_create", "can_edit", "can_delete", "can_admin")
            VALUES
                (:bucket_id, :auth_type, :identifier, :secret, :can_list, :can_read, :can_create, :can_edit, :can_delete, :can_admin)'
        );

        $secret_crypt = $secret ? password_hash($secret, PASSWORD_DEFAULT) : null;

        return $stmt->execute([
            "bucket_id"     => $bucket_id,
            "auth_type"     => $auth_type,
            "identifier"    => $identifier,
            "secret"        => $secret_crypt,
            "can_list"      => $auth_object->list ? 1 : 0,
            "can_read"      => $auth_object->read ? 1 : 0,
            "can_create"    => $auth_object->create ? 1 : 0,
            "can_edit"      => $auth_object->edit ? 1 : 0,
            "can_delete"    => $auth_object->delete ? 1 : 0,
            "can_admin"     => $auth_object->admin ? 1 : 0,
        ]);
    }


    public static function removeBucketAuth($bucket_name, $auth_type, $identifier = null, $secret = null)
    {
        $db = Database::getSingleton();

        $bucket_id = Bucket::getBucketID($bucket_name);

        if ($auth_type === "public") {
            $stmt = $db->prepare(
                'DELETE FROM auth
                WHERE "bucket_id" = :bucket_id
                    AND "auth_type" = :auth_type'
            );
            return $stmt->execute(["bucket_id" => $bucket_id, "auth_type" => $auth_type]);
        }

        if ($auth_type === "basic") {
            $stmt = $db->prepare(
                'DELETE FROM auth
                WHERE "bucket_id" = :bucket_id
                    AND "auth_type" = :auth_type
                    AND "identifier" = :identifier'
            );
            return $stmt->execute(["bucket_id" => $bucket_id, "auth_type" => $auth_type, "identifier" => $identifier]);
        }

        if ($auth_type === "bearer") {
            $stmt = $db->prepare(
                'DELETE FROM auth
                WHERE "bucket_id" = :bucket_id
                    AND "auth_type" = :auth_type
                    AND "secret" = :secret'
            );
            return $stmt->execute(["bucket_id" => $bucket_id, "auth_type" => $auth_type, "secret" => $secret]);
        }

        return false;
    }

    private static function resolveAuth(&$auth_object, $db_auth)
    {
        if ($db_auth['can_list']) {
            $auth_object->list = true;
        }
        if ($db_auth['can_read']) {
            $auth_object->read = true;
        }
        if ($db_auth['can_create']) {
            $auth_object->create = true;
        }
        if ($db_auth['can_edit']) {
            $auth_object->edit = true;
        }
        if ($db_auth['can_delete']) {
            $auth_object->delete = true;
        }
        if ($db_auth['can_admin']) {
            $auth_object->admin = true;
        }
    }
}
