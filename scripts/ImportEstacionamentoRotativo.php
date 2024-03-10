<?php

namespace Scripts;

use Drivers\ConnectionMysql;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;
use PDOException;

class ImportEstacionamentoRotativo extends Script
{
    /**
     * @var PDO
     */
    private $conn;

    const DAYS_LABEL = [
        'DOMINGO' => 'Sunday',
        'SEGUNDA' => 'Monday',
        'TERÇA' => 'Tuesday',
        'QUARTA' => 'Wednesday',
        'QUINTA' => 'Thursday',
        'SEXTA' => 'Friday',
        'SÁBADO' => 'Saturday'
    ];

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
        $imports = $this->getPendentImports();


        foreach ($imports as $import) {

            $this->conn->beginTransaction();

            try {

                $data = [];
                $this->extract($import, $data);
                $this->transformData($data);
                $this->loadData($import, $data);

                $this->updateDateProcessedCsvImports($import['id']);

                $this->conn->commit();

            } catch (PDOException $exception) {
                $this->conn->rollBack();
                echo "Error: " . $exception->getMessage();
            }

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

    /**
     * @param array $import
     * @param array $data
     * @return void
     * @throws GuzzleException
     */
    private function extract(array $import, array &$data)
    {
        $path = $import['path'];

        echo 'extract items' . PHP_EOL;

        $client = new Client([
            'headers' => [
                'User-Agent' => 'insomnia/2023.5.8',
            ],
        ]);

        $response = $client->get($path);
        $csv_file = $response->getBody()->getContents();
        $temp_file = tempnam(sys_get_temp_dir(), 'csv_');

        file_put_contents($temp_file, $csv_file);
        $file_handle = fopen($temp_file, 'r');

        $rows = [];
        $header = false;
        while (($line = fgets($file_handle)) !== false) {
            if (!$header) {
                $header = true;
                continue;
            }

            $row = str_getcsv($line);
            $rows[] = $row;
        }

        $data = $rows;
    }

    /**
     * @param array $data
     * @return void
     */
    private function transformData(array &$data)
    {
        echo 'transform data items' . PHP_EOL;

        $aux_data = [];
        foreach ($data as $key => $row) {

            # format time
            $time_permanence = '';
            $time_permanence_aux = $row[3];
            $time_permanence_aux_arr = explode(' ', $time_permanence_aux);
            if (isset($time_permanence_aux_arr[1])) {
                $type = $time_permanence_aux_arr[1];
                $original_number = $time_permanence_aux_arr[0];

                # horas
                if ($type === 'HORA(S)') {
                    $time_permanence = sprintf('%02d:00:00', (int)$original_number);
                }
            }

            # format period
            $period_label_aux = $row[7];
            $period_label_aux_arr = explode(' ÀS ', $period_label_aux);
            $time_period_start = '';
            if (isset($period_label_aux_arr[0])) {
                $time_period_start = $period_label_aux_arr[0];
            }
            $time_period_end = '';
            if (isset($period_label_aux_arr[1])) {
                $time_period_end = $period_label_aux_arr[1];
            }

            # format day
            $day_label_aux = $row[8];
            $day_label_aux_arr = explode(' A ', $day_label_aux);
            $day_start = '';
            if (isset($day_label_aux_arr[0])) {
                $day_start = $day_label_aux_arr[0];
                $day_start = self::DAYS_LABEL[$day_start];
            }
            $day_end = '';
            if (isset($day_label_aux_arr[1])) {
                $day_end = $day_label_aux_arr[1];
                $day_end = self::DAYS_LABEL[$day_end];
            }

            $aux = [
                'parking_id' => (int)$row[0],
                'vacancies_physical_count' => (int)$row[1],
                'vacancies_rotating_count' => (int)$row[2],
                'time_permanence_label' => $row[3],
                'time_permanence' => $time_permanence,
                'public_place' => $row[4],
                'reference' => $row[5],
                'neighborhood' => $row[6],
                'period_label' => $row[7],
                'time_period_start' => $time_period_start,
                'time_period_end' => $time_period_end,
                'day_label' => $row[8],
                'day_start' => $day_start,
                'day_end' => $day_end,
                'polygon' => $row[9]
            ];


            $aux_data[$key] = $aux;

        }

        $data = $aux_data;
    }

    private function loadData(array $import, array $data)
    {
        echo 'save items by import' . PHP_EOL;

        $cols = [
            'csv_import_id',
            'parking_id',
            'vacancies_physical_count',
            'vacancies_rotating_count',
            'time_permanence_label',
            'time_permanence',
            'public_place',
            'reference',
            'neighborhood',
            'period_label',
            'time_period_start',
            'time_period_end',
            'day_label',
            'day_start',
            'day_end',
            'polygon',
        ];

        $cols_aux = [];
        foreach ($cols as $col) {
            $cols_aux[] = $col;
            #$cols_aux[] = sprintf("'%s'", $col);
        }
        $sql_cols = implode(',', $cols_aux);

        $sql_values = [];
        foreach ($cols as $col) {
            $sql_values[] = sprintf(':%s', $col);
        }
        $sql_values = implode(',', $sql_values);

        $query = <<<SQL
        INSERT INTO parking_lots ({$sql_cols})
        VALUES ({$sql_values})
SQL;

        $stmt = $this->conn->prepare($query);
        foreach ($data as $row) {

            echo 'time: ' . date('Y-m-d H:i:s') . PHP_EOL;

            $row['csv_import_id'] = $import['id'];

            $stmt->bindParam(':csv_import_id', $row['csv_import_id']);
            $stmt->bindParam(':parking_id', $row['parking_id']);
            $stmt->bindParam(':vacancies_physical_count', $row['vacancies_physical_count']);
            $stmt->bindParam(':vacancies_rotating_count', $row['vacancies_rotating_count']);
            $stmt->bindParam(':time_permanence_label', $row['time_permanence_label']);
            $stmt->bindParam(':time_permanence', $row['time_permanence']);
            $stmt->bindParam(':public_place', $row['public_place']);
            $stmt->bindParam(':reference', $row['reference']);
            $stmt->bindParam(':neighborhood', $row['neighborhood']);
            $stmt->bindParam(':period_label', $row['period_label']);
            $stmt->bindParam(':time_period_start', $row['time_period_start']);
            $stmt->bindParam(':time_period_end', $row['time_period_end']);
            $stmt->bindParam(':day_label', $row['day_label']);
            $stmt->bindParam(':day_start', $row['day_start']);
            $stmt->bindParam(':day_end', $row['day_end']);
            $stmt->bindParam(':polygon', $row['polygon']);

            $stmt->execute();

        }
    }

    private function updateDateProcessedCsvImports(int $id)
    {
        echo 'get pendent items' . PHP_EOL;

        $query = <<<SQL
        UPDATE csv_imports t
        SET t.date_processed = CURRENT_DATE
        WHERE t.id = :id;
SQL;

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }
}