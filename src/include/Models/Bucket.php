<?php

namespace KVStore\Models;

use KVStore\Database;
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
            WHERE bucket_name = :name';
        $params = ["name" => $this->name];

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
                "type"
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
                "type"
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
            return new \DateTime($result);
        }

        $stmt = $db->prepare(
            'SELECT "created_at"
            FROM buckets
            WHERE "bucket_name" = :name'
        );
        $stmt->execute(["name" => $this->name]);

        $result = $stmt->fetchColumn();

        if ($result) {
            return new \DateTime($result);
        }

        throw new \Exception("Bucket not found $this->name");
    }

    /**
     * @param string $key
     * @param object|string $object
     */
    public function createObject($key, $object)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('INSERT INTO objects ("bucket_id", "key", "value", "numeric_value", "type") VALUES (:bucket_id, :key, :value, :number, :type)');

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
        ]);
    }

    /**
     * @param string $key
     * @param object $object
     */
    public function editObject($key, $object)
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
