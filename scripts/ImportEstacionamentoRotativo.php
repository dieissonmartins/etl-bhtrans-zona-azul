<?php

namespace Scripts;

use Drivers\ConnectionMysql;
use Exception;
use PDO;

class ImportEstacionamentoRotativo extends Script
{
    /**
     * @var PDO
     */
    private $conn;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->conn = (new ConnectionMysql())->open();
    }

    /**
     * @return bool
     */
    public function runScript(): bool
    {
        $imports = $this->getPendentImports();

        foreach ($imports as $import) {

            $debug = $import;

        }

        return true;
    }

    /**
     * @return array
     */
    private function getPendentImports(): array
    {
        echo 'get pendent items' . PHP_EOL;

        $query = <<<SQL
        SELECT a.id     as 'id'
             , a.status as 'status'
             , a.date   as 'date'
             , a.path   as 'path'
        FROM csv_imports a
        WHERE a.status = :status
        ORDER BY a.date;
        ;
SQL;

        $status = 0;

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}