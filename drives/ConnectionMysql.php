<?php

namespace Src\database;

use PDO;
use Exception;
use PDOException;

class ConnectionMysql
{

    /**
     * @throws Exception
     */
    public static function open(): PDO
    {

        $fileName = ".env";

        if (file_exists(__DIR__ . "/$fileName.ini")) {
            $db = parse_ini_file(__DIR__ . "/../config/$fileName.ini");
        } else {
            throw new Exception("file '$fileName' not fold", 1);

        }

        $user = $db['DB_USERNAME'] ?? null;
        $pass = $db['DB_PASSWORD'] ?? null;
        $name = $db['DB_DATABASE'] ?? null;
        $host = $db['DB_HOST'] ?? null;
        $type = $db['DB_CONNECTION'] ?? null;
        $port = $db['DB_PORT'] ?? null;

        $conn = null;
        if ($type == 'mysql') {
            try {
                $port = $port ?: '3306';
                $conn = new PDO("mysql:host=$host;port=$port;dbname=$name", $user, $pass);
            } catch (PDOException $e) {
                echo "not connected database..." . $e->getMessage();
            }
        }

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $conn;
    }
}