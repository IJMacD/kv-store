<?php

namespace KVStore;

class Database
{
    /**
     * @var \PDO
     */
    private $db;
    private static $singleton;
    private string $driver;

    private function __construct()
    {
        $this->db = new \PDO(getenv("DATABASE_DSN"), getenv("DATABASE_USER"), getenv("DATABASE_PASS"), [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode = 'ANSI'"
        ]);
        $this->driver = explode(":", getenv("DATABASE_DSN"))[0];
    }

    public function prepare($sql)
    {
        return $this->db->prepare($sql);
    }

    public function getDriver() {
        return $this->driver;
    }

    /**
     * @return self
     */
    public static function getSingleton()
    {
        if (!self::$singleton) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }
}
