<?php

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
    public function getObjectKeys($since = null)
    {
        $db = Database::getSingleton();

        $sql =
            'SELECT
                "key"
            FROM objects
                JOIN buckets USING (bucket_id)
            WHERE "bucket_name" = :name
            ';
        $params = ["name" => $this->name];

        if ($since) {
            $sql .= ' AND "created_at" > :since';
            $params["since"] = $since;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($result) {
            return $result;
        }

        return [];
    }

    public function getObjectsMeta($since = null)
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

    public function getObjects($since = null)
    {
        $db = Database::getSingleton();

        $sql =
            'SELECT
                "key",
                "value",
                "objects"."created_at"
            FROM objects
                JOIN buckets USING (bucket_id)
            WHERE "bucket_name" = :name';
        $params = ["name" => $this->name];

        if ($since) {
            $sql .= ' AND "created_at" > :since';
            $params["since"] = $since;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

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
                "value",
                "objects"."created_at"
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
            return new DateTime($result);
        }

        $stmt = $db->prepare(
            'SELECT "created_at"
            FROM buckets
            WHERE "bucket_name" = :name'
        );
        $stmt->execute(["name" => $this->name]);

        $result = $stmt->fetchColumn();

        if ($result) {
            return new DateTime($result);
        }

        throw new Exception("Bucket not found $this->name");
    }

    /**
     * @param string $key
     * @param object $object
     */
    public function createObject($key, $object)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('INSERT INTO objects ("bucket_id", "key", "value") VALUES (:bucket_id, :key, :value)');

        // TODO: validate type
        return $stmt->execute(["bucket_id" => $bucket_id, "key" => $key, "value" => json_encode($object)]);
    }

    /**
     * @param string $key
     * @param object $object
     */
    public function editObject($key, $object)
    {
        $db = Database::getSingleton();

        $bucket_id = self::getBucketID($this->name);

        $stmt = $db->prepare('SELECT COUNT(*) FROM objects WHERE "bucket_id" = :id AND "key" = :key');
        $stmt->execute(["id" => $bucket_id, "key" => $key]);

        if ($stmt->fetchColumn() == 0) {
            throw new Exception("[Bucket] Cannot edit object which does not exist: " . $this->name . "/" . $key);
        }

        $stmt = $db->prepare('UPDATE objects SET "value" = :value, "created_at" = CURRENT_TIMESTAMP WHERE "bucket_id" = :id AND "key" = :key');

        // TODO: validate type against data already in database
        return $stmt->execute(["id" => $bucket_id, "key" => $key, "value" => json_encode($object)]);
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
