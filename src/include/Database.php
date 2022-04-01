<?php

class Database {
    /**
     * @var PDO
     */
    private $db;
    private static $singleton;

    private function __construct () {
        $this->db = new PDO(DATABASE_DSN, DATABASE_USER, DATABASE_PASS, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        try {
            $this->db->exec("SELECT name FROM Buckets");
        } catch (Exception $e) {
            $this->createDatabase();
        }
    }

    public function prepare ($sql) {
        return $this->db->prepare($sql);
    }

    private function createDatabase () {
        $create_sql = file_get_contents(__DIR__ . "/create.sql");
        $statements = explode(";", $create_sql);

        try {
            foreach ($statements as $statement) {
                $this->db->exec($statement);
            }
        } catch (Exception $e) {
            $this->db->exec("ROLLBACK");
            throw new Exception ("Unable to create Database");
        }
    }

    /**
     * @return self
     */
    public static function getSingleton () {
        if (!self::$singleton) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }
}