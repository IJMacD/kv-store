<?php

namespace KVStore\Models;

use KVStore\Database;
use KVStore\DateTimeJSON;
use PDO;

class Bucket
{
    var $name;

    private function __construct($bucket_name)
    {
        $this->name = $bucket_name;
    }

    /**
     * Get an array of keys in this bucket
     */
    public function getObjectKeys($since = null, $limit = 10000, $prefix = null)
    {
        $db = Database::getSingleton();

        $where = '"bucket_name" = :name';

        if ($since) {
            $where .= ' AND "created_at" > :since';
        }

        if ($prefix) {
            $where .= ' AND "key" LIKE :prefix';
        }

        $sql =
            'SELECT
                "key"
            FROM objects
                JOIN buckets USING (bucket_id)
            WHERE ' . $where . '
            LIMIT :limit
            ';

        $stmt = $db->prepare($sql);

        $stmt->bindValue("name", $this->name);
        $stmt->bindValue("limit", $limit, PDO::PARAM_INT);

        if ($since) {
            $stmt->bindValue("since", $since);
        }

        if ($prefix) {
            $stmt->bindValue("prefix", $prefix . "%");
        }

        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if ($result) {
            return $result;
        }

        return [];
    }

    // TODO: handle extra params
    public function getObjectsMeta($since = null, $limit = 10000, $prefix = null)
    {
        $db = Database::getSingleton();

        $sql =
            'SELECT
                "key",
                "objects"."created_at"
            FROM objects
                JOIN buckets USING (bucket_id)
            WHERE bucket_name = :name
            LIMIT :limit';
        $params = ["name" => $this->name, "limit" => $limit];

        if ($since) {
            $sql .= ' AND "created_at" > :since';
            $params["since"] = $since;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetchAll();

        if ($result) {
            return $result;
        }

        return [];
    }

    public function getObjects($since = null, $limit = 10000, $prefix = null)
    {
        $db = Database::getSingleton();

        $where = '"bucket_name" = :name';

        if ($since) {
            $where .= ' AND "created_at" > :since';
        }

        if ($prefix) {
            $where .= ' AND "key" LIKE :prefix';
        }

        $sql =
            'SELECT
                "key",
                COALESCE("value","numeric_value") AS "value",
                "objects"."created_at",
                "type",
                "mime"
            FROM objects
                JOIN buckets USING (bucket_id)
            WHERE ' . $where . '
            ORDER BY "created_at"
            LIMIT :limit
            ';


        $stmt = $db->prepare($sql);

        $stmt->bindValue("name", $this->name);
        $stmt->bindValue("limit", $limit, PDO::PARAM_INT);

        if ($since) {
            $stmt->bindValue("since", $since);
        }

        if ($prefix) {
            $stmt->bindValue("prefix", $prefix . "%");
        }

        $stmt->execute();

        $result = $stmt->fetchAll();

        if ($result) {
            return array_map([BucketObject::class, "fromArray"], $result);
        }

        return [];
    }

    public function getObject($key)
    {
        $db = Database::getSingleton();

        $stmt = $db->prepare(
            'SELECT
                "key",
                COALESCE("value","numeric_value") AS "value",
                "objects"."created_at",
                "type",
                "mime"
            FROM objects
                JOIN buckets USING (bucket_id)
            WHERE "bucket_name" = :name
                AND "key" = :key'
        );
        $stmt->execute(["name" => $this->name, "key" => $key]);

        $result = $stmt->fetch();

        if ($result) {
            return BucketObject::fromArray($result);
        }

        return null;
    }

    public function getLastModifiedDate()
    {
        $db = Database::getSingleton();

        $stmt = $db->prepare(
            'SELECT
                MAX("objects"."created_at")
            FROM objects
                JOIN buckets USING (bucket_id)
            WHERE "bucket_name" = :name'
        );
        $stmt->execute(["name" => $this->name]);

        $result = $stmt->fetchColumn();

        if ($result) {
            return new DateTimeJSON($result);
        }

        $stmt = $db->prepare(
            'SELECT "created_at"
            FROM buckets
            WHERE "bucket_name" = :name'
        );
        $stmt->execute(["name" => $this->name]);

        $result = $stmt->fetchColumn();

        if ($result) {
            return new DateTimeJSON($result);
        }

        throw new \Exception("Bucket not found $this->name");
    }

    /**
     * @param string $key
     * @param object|string $object
     * @param string $mime
     */
    public function createObject($key, $object, $mime = "text/plain")
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('INSERT INTO objects ("bucket_id", "key", "value", "numeric_value", "type", "mime") VALUES (:bucket_id, :key, :value, :number, :type, :mime)');

        $value = null;
        $number = null;
        $type = "TEXT";

        if (is_array($object) || is_object($object)) {
            $value = json_encode($object);
            $type = "JSON";
        } else if (is_numeric($object)) {
            $number = $object;
            $type = "NUMBER";
        } elseif (json_decode($object)) {
            $value = $object;
            $type = "JSON";
        } else {
            $value = $object;
            $type = "TEXT";
        }

        return $stmt->execute([
            "bucket_id" => $bucket_id,
            "key" => $key,
            "value" => $value,
            "number" => $number,
            "type" => $type,
            "mime" => $mime,
        ]);
    }

    /**
     * @param string $key
     * @param object|string $object
     * @param string $mime
     */
    public function editObject($key, $object, $mime)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('SELECT "type" FROM objects WHERE "bucket_id" = :id AND "key" = :key');
        $stmt->execute(["id" => $bucket_id, "key" => $key]);

        if ($stmt->rowCount() == 0) {
            throw new \Exception("[Bucket] Cannot edit object which does not exist: " . $this->name . "/" . $key);
        }

        $type = $stmt->fetchColumn();

        $stmt = $db->prepare('UPDATE objects SET "value" = :value, "created_at" = CURRENT_TIMESTAMP WHERE "bucket_id" = :id AND "key" = :key');

        // TODO: validate type if numeric
        // TODO: check if ($type !== "JSON" && !is_string($object))
        // TODO: check if mimes match

        return $stmt->execute(["id" => $bucket_id, "key" => $key, "value" => $type === "JSON" ? json_encode($object) : $object]);
    }

    /**
     * @param string $key
     */
    public function deleteObject($key)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('DELETE FROM objects WHERE "bucket_id" = :id AND "key" = :key');
        return $stmt->execute(["id" => $bucket_id, "key" => $key]);
    }

    public static function get($bucket_name)
    {
        $db = Database::getSingleton();

        $stmt = $db->prepare('SELECT bucket_name FROM buckets WHERE "bucket_name" = :name');
        $stmt->execute(["name" => $bucket_name]);
        $result = $stmt->fetch();

        if ($result) {
            return new self($bucket_name);
        }

        return null;
    }

    public static function list($email, $label = null)
    {
        $db = Database::getSingleton();

        $sql = 'SELECT bucket_name FROM buckets WHERE "email" = :email';
        $params = ["email" => $email];

        if ($label) {
            $sql .= 'AND "label" = :label';
            $params["label"] = $label;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($results) {
            return $results;
        }

        return [];
    }

    public static function create($bucket_name, $email = null)
    {
        $db = Database::getSingleton();

        $stmt = $db->prepare('INSERT INTO buckets ("bucket_name", "email") VALUES (:name, :email)');
        return $stmt->execute(["name" => $bucket_name, "email" => $email]);
    }

    public static function getBucketID($bucket_name)
    {
        $db = Database::getSingleton();

        $bucket_stmt = $db->prepare('SELECT "bucket_id" FROM buckets WHERE "bucket_name" = :bucket_name');
        $bucket_stmt->execute(["bucket_name" => $bucket_name]);
        return $bucket_stmt->fetchColumn();
    }
}
