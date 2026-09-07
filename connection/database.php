<?php

require_once __DIR__ . '/../config/database.php';

class DB
{
    private static array $instances = [];
    private PDO $pdo;

    private function __construct(string $server = 'main_db')
    {
        $servers = DB_SERVERS;

        if (!isset($servers[$server])) {
            throw new Exception("Database server '{$server}' is not configured.");
        }

        $config = $servers[$server];

        $dsn = "sqlsrv:Server="
            . $config['host']
            . ","
            . $config['port']
            . ";Database="
            . $config['database']
            . ";TrustServerCertificate=1;";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
        ];

        $this->pdo = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $options
        );
    }

    public static function get_connection(
        string $server = 'main_db'
    ): PDO {
        if (!isset(self::$instances[$server])) {
            self::$instances[$server] = new self($server);
        }

        return self::$instances[$server]->pdo;
    }
}
