<?php

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $defaults = [
                'host' => 'localhost',
                'port' => '5432',
                'name' => 'devmercado',
                'user' => 'postgres',
                'pass' => '',
            ];
            $configFile = __DIR__ . '/config.php';
            $config = is_file($configFile)
                ? array_merge($defaults, require $configFile)
                : $defaults;

            $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['name']}";

            self::$instance = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$instance;
    }
}
