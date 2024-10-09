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

    public function getObject(string $key)
    {
        $db = Database::getSingleton();

        if ($db->getDriver() === "pgsql") {
            $stmt = $db->prepare(
                'SELECT
                    "key",
                    COALESCE(
                        "numeric_value"::varchar,
                        "text_value"
                    ) AS "value",
                    "binary_value",
                    "objects"."created_at",
                    "mime"
                FROM objects
                    JOIN buckets USING (bucket_id)
                WHERE "bucket_name" = :name
                    AND "key" = :key'
            );
        } else {
            $stmt = $db->prepare(
                'SELECT
                    "key",
                    COALESCE("numeric_value","text_value") AS "value",
                    "binary_value",
                    "objects"."created_at",
                    "mime"
                FROM objects
                    JOIN buckets USING (bucket_id)
                WHERE "bucket_name" = :name
                    AND "key" = :key'
            );
        }

        $stmt->execute(["name" => $this->name, "key" => $key]);

        $result = $stmt->fetch();

        if ($result) {
            if ($result["binary_value"] !== null) {
                $result["value"] = $result["binary_value"];
            }
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

    public function createObject(string $key, mixed $object, string $mime = null)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('INSERT INTO objects ("bucket_id", "key", "text_value", "numeric_value", "binary_value", "mime") VALUES (:bucket_id, :key, :value, :number, :binary, :mime)');

        $stmt->bindParam(":bucket_id", $bucket_id);
        $stmt->bindParam(":key", $key);
        $null = null;
        $stmt->bindParam(":value", $null, PDO::PARAM_NULL);
        $stmt->bindParam(":number", $null, PDO::PARAM_NULL);
        $stmt->bindParam(":binary", $null, PDO::PARAM_NULL);

        if (str_contains($object, "\x00")) {
            $stmt->bindParam(":binary", $object, PDO::PARAM_LOB);
        } else if (is_array($object) || is_object($object)) {
            $stmt->bindParam(":value", json_encode($object));
            if ($mime == null) {
                $mime = "application/json";
            }
        } else if (is_numeric($object)) {
            $stmt->bindParam(":number", $object);
        } else {
            $stmt->bindParam("value", $object);
        }

        if ($mime == null) {
            $mime = "text/plain";
        }

        $stmt->bindParam(":mime", $mime);

        return $stmt->execute();
    }

    public function editObject(string $key, mixed $object, string $mime)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('SELECT "mime" FROM objects WHERE "bucket_id" = :id AND "key" = :key');
        $stmt->execute(["id" => $bucket_id, "key" => $key]);

        if ($stmt->rowCount() == 0) {
            throw new \Exception("[Bucket] Cannot edit object which does not exist: {$this->name}/{$key}");
        }

        $db_mime = $stmt->fetchColumn();

        if ($db_mime !== $mime) {
            throw new \Exception("[Bucket] Cannot change the mime type of an object.");
        }

        if (str_contains($object, "\x00")) {
            $stmt = $db->prepare('UPDATE objects SET "binary_value" = :value, "created_at" = CURRENT_TIMESTAMP WHERE "bucket_id" = :id AND "key" = :key');
            $stmt->bindParam(":value", $object, PDO::PARAM_LOB);
        } else if (is_numeric($object)) {
            $stmt = $db->prepare('UPDATE objects SET "numeric_value" = :value, "created_at" = CURRENT_TIMESTAMP WHERE "bucket_id" = :id AND "key" = :key');
            $stmt->bindParam(":value", $object);
        } else {
            $stmt = $db->prepare('UPDATE objects SET "text_value" = :value, "created_at" = CURRENT_TIMESTAMP WHERE "bucket_id" = :id AND "key" = :key');
            $stmt->bindParam(":value", is_object($object) ? json_encode($object) : $object);
        }

        $stmt->bindParam(":id", $bucket_id);
        $stmt->bindParam(":key", $key);

        return $stmt->execute();
    }

    public function deleteObject(string $key)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('DELETE FROM objects WHERE "bucket_id" = :id AND "key" = :key');
        return $stmt->execute(["id" => $bucket_id, "key" => $key]);
    }

    /**
     * @return int -1 if object does not exist
     */
    public function getObjectLength(string $key): int
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('SELECT LENGTH("text_value") FROM objects WHERE "bucket_id" = :id AND "key" = :key');
        $stmt->execute(["id" => $bucket_id, "key" => $key]);

        if ($stmt->rowCount() < 1) {
            return -1;
        }

        return $stmt->fetchColumn();
    }

    public function patchNumericObject(string $key, float $delta): int
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        if ($db->getDriver() === "pgsql") {
            $stmt = $db->prepare('UPDATE objects SET numeric_value = numeric_value + :delta WHERE "bucket_id" = :id AND "key" = :key AND numeric_value IS NOT NULL RETURNING numeric_value');

            $stmt->execute(["id" => $bucket_id, "key" => $key, "delta" => $delta]);

            return $stmt->fetchColumn();
        }

        $stmt = $db->prepare('UPDATE objects SET numeric_value = numeric_value + :delta WHERE "bucket_id" = :id AND "key" = :key AND numeric_value IS NOT NULL');

        $stmt->execute(["id" => $bucket_id, "key" => $key, "delta" => $delta]);

        $stmt = $db->prepare('SELECT numeric_value FROM objects WHERE "bucket_id" = :id AND "key" = :key AND numeric_value IS NOT NULL');

        return $stmt->fetchColumn();
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
