<?php

namespace Scripts;

use Drivers\ConnectionMysql;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;
use PDOException;

class DownloadCsvBhTrans extends Script
{
    const SITE_BH_TRANS_PATH_FILE = 'https://ckan.pbh.gov.br/dataset/f1d9ca7f-32e0-409f-a5c2-c8aea9f97c37/resource/f86a934d-75db-471b-8a74-419577ccfe68/download/';

    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var array
     */
    private $imports_cache = [];

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->conn = (new ConnectionMysql())->open();
    }

    /**
     * @return bool
     * @throws GuzzleException
     */
    public function runScript(): bool
    {
        $files = [];

        $path = self::SITE_BH_TRANS_PATH_FILE;
        $final_file = '_estacionamento_rotativo.csv';

        $start = date('Ymd', strtotime('2024-03-01'));
        $today = date('Ymd');

        $date_aux = $start;

        while ($date_aux <= $today) {

            $url = sprintf('%s%s%s', $path, $today, $final_file);
            if ($this->urlExists($url)) {
                $files[$date_aux] = [
                    'file_name' => sprintf('%s%s', $date_aux, $final_file),
                    'path' => $url
                ];
            }

            $date_aux = date('Ymd', strtotime($date_aux . ' +1 day'));
        }

        $this->conn->beginTransaction();

        try {

            $this->importsCache();

            foreach ($files as $file) {
                $file_name = $file['file_name'];

                $data = [
                    'status' => false,
                    'date' => date('Y-m-d'),
                    'file_name' => $file_name,
                    'path' => $file['path']
                ];

                if (isset($this->imports_cache[$file_name])) {
                    echo 'file really exist' . PHP_EOL;
                    continue;
                }

                $this->saveRow($data);
            }

            $this->conn->commit();

        } catch (PDOException $exception) {
            $this->conn->rollBack();
            echo "Error: " . $exception->getMessage();
        }

        return true;
    }

    /**
     * @param $url
     * @return bool
     * @throws GuzzleException
     */
    private function urlExists($url): bool
    {
        echo $url . PHP_EOL;

        $client = new Client([
            'headers' => [
                'User-Agent' => 'insomnia/2023.5.8',
            ],
        ]);

        $response = $client->get($url);

        $content = $response->getBody()->getContents();
        if ($content) {
            echo 'file exist TRUE' . PHP_EOL;
            return true;
        } else {
            echo 'file exist FALSE' . PHP_EOL;
            return false;
        }
    }

    /**
     * @param array $data
     * @return void
     */
    private function saveRow(array $data)
    {
        echo 'save item by import' . PHP_EOL;

        $query = <<<SQL
        INSERT INTO csv_imports (date, file_name, path)
        VALUES (:date, :file_name, :path)
SQL;


        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $data['date']);
        $stmt->bindParam(':file_name', $data['file_name']);
        $stmt->bindParam(':path', $data['path']);
        $stmt->execute();
    }

    private function importsCache()
    {
        $query = <<<SQL
        SELECT a.file_name as 'name'
        FROM csv_imports a
        WHERE a.date > :date;
SQL;

        $date = '2024-03-01';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->execute();

        $data = $stmt->fetchAll();

        foreach ($data as $row) {
            $name = $row['name'];
            $this->imports_cache[$name] = $name;
        }
    }
}