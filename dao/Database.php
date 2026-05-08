<?php

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host = 'localhost';
            $port = '5432';
            $name = 'devmercado';
            $user = 'postgres';
            $pass = 'ucs';

            $dsn = "pgsql:host=$host;port=$port;dbname=$name";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$instance;
    }
}
