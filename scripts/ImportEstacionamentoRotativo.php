<?php

namespace Scripts;

use Drivers\ConnectionMysql;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;

class ImportEstacionamentoRotativo extends Script
{
    /**
     * @var PDO
     */
    private $conn;

    const DAYS_LABEL = [
        'DOMINGO' => 'Sunday',
        'SEGUNDA' => 'Monday',
        'TERCA' => 'Tuesday',
        'QUARTA' => 'Wednesday',
        'QUINTA' => 'Thursday',
        'SEXTA' => 'Friday',
        'SABADO' => 'Saturday'
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
            $data = [];

            $this->extract($import, $data);

            $this->transformData($data);
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
}