<?php

namespace Drivers;

use Dotenv\Dotenv;
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

        $paths = __DIR__ . '/../';

        $dotenv = Dotenv::createImmutable($paths);
        $dotenv->load();

        $user = $_ENV['DB_USERNAME'];
        $pass = $_ENV['DB_PASSWORD'];
        $name = $_ENV['DB_DATABASE'];
        $host = $_ENV['DB_HOST'];
        $type = $_ENV['DB_CONNECTION'];
        $port = $_ENV['DB_PORT'];

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