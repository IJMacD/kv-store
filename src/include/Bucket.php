<?php

class Bucket {
    var $name;

    private function __construct($bucket_name)
    {
        $this->name = $bucket_name;
    }

    /**
     * Get an array of keys in this bucket
     */
    public function getObjectKeys ($since = null) {
        $db = Database::getSingleton();

        $sql = "SELECT key FROM Objects WHERE bucket_name = :name";
        $params = [ "name" => $this->name ];

        if ($since) {
            $sql .= " AND created_date > :since";
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

    public function getObjectsMeta ($since = null) {
        $db = Database::getSingleton();

        $sql = "SELECT key, created_date FROM Objects WHERE bucket_name = :name";
        $params = [ "name" => $this->name ];

        if ($since) {
            $sql .= " AND created_date > :since";
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

    public function getObjects ($since = null) {
        $db = Database::getSingleton();

        $sql = "SELECT key, value, created_date FROM Objects WHERE bucket_name = :name";
        $params = [ "name" => $this->name ];

        if ($since) {
            $sql .= " AND created_date > :since";
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

    public function getObject ($key) {
        $db = Database::getSingleton();

        $stmt = $db->prepare("SELECT key, value, created_date FROM Objects WHERE bucket_name = :name AND key = :key");
        $stmt->execute([ "name" => $this->name, "key" => $key ]);

        $result = $stmt->fetch();

        if ($result) {
            return BucketObject::fromArray($result);
        }

        return null;
    }

    public function getLastModifiedDate () {
        $db = Database::getSingleton();

        $stmt = $db->prepare("SELECT MAX(created_date) FROM Objects WHERE bucket_name = :name ");
        $stmt->execute([ "name" => $this->name ]);

        $result = $stmt->fetchColumn();

        if ($result) {
            return new DateTime($result);
        }

        return null;
    }

    /**
     * @param string $key
     * @param object $object
     */
    public function createObject ($key, $object) {
        $db = Database::getSingleton();

        $stmt = $db->prepare("INSERT INTO Objects (bucket_name, key, value) VALUES (:name, :key, :value)");
        return $stmt->execute([ "name" => $this->name, "key" => $key, "value" => json_encode($object) ]);
    }

    /**
     * @param string $key
     * @param object $object
     */
    public function editObject ($key, $object) {
        $db = Database::getSingleton();

        $stmt = $db->prepare("SELECT COUNT(*) FROM Objects WHERE bucket_name = :name AND key = :key");
        $stmt->execute([ "name" => $this->name, "key" => $key ]);

        if ($stmt->fetchColumn() == 0) {
            throw new Exception("[Bucket] Cannot edit object which does not exist: " . $this->name . "/" . $key);
        }

        $stmt = $db->prepare("UPDATE Objects SET value = :value, created_date = CURRENT_TIMESTAMP WHERE bucket_name = :name AND key = :key");
        return $stmt->execute([ "name" => $this->name, "key" => $key, "value" => json_encode($object) ]);
    }

    /**
     * @param string $key
     */
    public function deleteObject ($key) {
        $db = Database::getSingleton();

        $stmt = $db->prepare("DELETE FROM Objects WHERE bucket_name = :name AND key = :key");
        return $stmt->execute([ "name" => $this->name, "key" => $key ]);
    }

    public static function get ($bucket_name) {
        $db = Database::getSingleton();

        $stmt = $db->prepare("SELECT name FROM Buckets WHERE name = :name");
        $stmt->execute([ "name" => $bucket_name ]);
        $result = $stmt->fetch();

        if ($result) {
            return new self($bucket_name);
        }

        return null;
    }

    public static function create ($bucket_name) {
        $db = Database::getSingleton();

        $stmt = $db->prepare("INSERT INTO Buckets (name) VALUES (:name)");
        return $stmt->execute([ "name" => $bucket_name ]);
    }
}
