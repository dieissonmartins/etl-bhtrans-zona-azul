<?php

namespace Scripts;

use Drivers\ConnectionMysql;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;
use PDOException;

class DownloadCsvEstacionamentoRotativo extends Script
{
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
        $html_page = $this->getHTMLPage();

        $paths = $this->getPaths($html_page);

        echo 'process paths' . PHP_EOL;

        $files = [];
        foreach ($paths as $path) {

            echo 'Path: ' . $path . PHP_EOL;

            $path_parts = explode('/', $path);

            # set file name
            $file_name = end($path_parts);

            # set date
            $file_name_parts = explode('_', $file_name);
            $date = $file_name_parts[0];

            $files[$file_name] = [
                'status' => 0,
                'date' => date('Y-m-d', strtotime($date)),
                'file_name' => $file_name,
                'path' => $path
            ];
        }

        if (!$files) {
            return false;
        }

        $this->conn->beginTransaction();

        try {

            $this->importsCache();

            foreach ($files as $file) {
                $file_name = $file['file_name'];

                if (isset($this->imports_cache[$file_name])) {
                    echo 'file really exist' . PHP_EOL;
                    continue;
                }

                $this->saveRow($file);
            }

            $this->conn->commit();

        } catch (PDOException $exception) {
            $this->conn->rollBack();
            echo "Error: " . $exception->getMessage();
        }

        return true;
    }

    /**
     * @param array $data
     * @return void
     */
    private function saveRow(array $data)
    {
        echo 'save item by import' . PHP_EOL;

        $query = <<<SQL
        INSERT INTO csv_imports (status, date, file_name, path)
        VALUES (:status, :date, :file_name, :path)
SQL;


        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $data['status']);
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
SQL;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $data = $stmt->fetchAll();

        foreach ($data as $row) {
            $name = $row['name'];
            $this->imports_cache[$name] = $name;
        }
    }

    /**
     * @return string
     * @throws GuzzleException
     */
    private function getHTMLPage(): string
    {
        echo 'get HTML by page' . PHP_EOL;

        $client = new Client([
            'headers' => [
                'User-Agent' => 'insomnia/2023.5.8',
            ],
        ]);

        $url = 'https://ckan.pbh.gov.br/dataset/estacionamento-rotativo';
        $response = $client->get($url);
        $content = $response->getBody()->getContents();

        return $content;
    }

    /**
     * @param string $html
     * @return mixed
     */
    private function getPaths(string $html): array
    {
        echo 'extract link with .csv' . PHP_EOL;

        $pattern = '/<a\s+.*?href=["\']([^"\']+)["\'].*?>/i';
        $matches = [];

        preg_match_all($pattern, $html, $matches);

        $hrefs = $matches[1];

        $paths = [];
        foreach ($hrefs as $key => $href) {

            $href_arr = explode('.', $href);
            $ext = end($href_arr);

            if ($ext == 'csv') {
                $paths[$key] = $href;
            }
        }

        return $paths;
    }
}