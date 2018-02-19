<?php

namespace Ghost;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Magento2\Resource\Auth;
use Sql\Sql;

class Inspector
{
    const KEY = "<KEY>";
    const URL = "https://api.ghostinspector.com/v1/";

    public function getClient()
    {
        $client = new Client([
            "base_uri"      =>      self::URL,
        ]);

        return $client;
    }

    public function query($client, $string)
    {
        $res = $client->get($string . self::KEY);

        $json = $res->getBody()->getContents();

        $result = json_decode($json, true);

        return $result;
    }

    function getTests()
    {
        $sql = new Sql;
        $mysql = $sql->getNewInstance("user");
        $mysql->connect();
        $mysql->setTables([
            "table"        =>      "database",
        ]);


        $query = "SELECT *
        FROM {table}";

        return $sqlConnection->query($query);
    }

    function getTest($rowId)
    {
        $sql = new Sql;
        $mysql = $sql->getNewInstance("user");
        $mysql->connect();
        $mysql->setTables([
            "table"        =>      "database",
        ]);

        $query = "SELECT *
        FROM {table}
        WHERE row_id = ?rowId";

        $params = [
            "rowId"     =>      $rowId,
        ];

        return $sqlConnection->query($query, $params);
    }

    function getUsefulData($ghost, $client, array $ghostId)
    {
        $results = $ghost->query($client, "tests/" . $ghostId["test_id"] . "/results/");

        $returnedData = [];
        $returnedData = [
            "testId"        =>      $results["data"][0]["test"]["_id"],
            "testSuite"     =>      $results["data"][0]["test"]["suite"],
            "testName"      =>      $results["data"][0]["test"]["name"],
            "video"         =>      $results["data"][0]["video"]["url"],
        ];

        return $returnedData;
    }

    function getTestSummary($ghost, $client, array $ghostId)
    {
        $results = $ghost->query($client, "tests/" . $ghostId["test_id"] . "/results/");

        $returnedData = [];
        foreach($results["data"][0]["steps"] as $steps) {
            $testData = [];
            $keys = ["target", "command", "error"];

            foreach ($keys as $key) {
                if (array_key_exists($key, $steps)) {
                    $testData[$key] = $steps[$key];
                }  
            }
            $returnedData[] = $testData;
        }

        return $returnedData;
    }
}
