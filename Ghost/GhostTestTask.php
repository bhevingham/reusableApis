<?php

namespace Ghost;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ghost\Inspector;
use Sql\Sql;
use AwsApi\Factory;

class TestSuiteTask extends Command
{
    const URL = "https://app.ghostinspector.com/";

    protected function configure()
    {
        $this
            ->setName("ghost:test-suite")
            ->setDescription("Fetch, list and execute a suite test")
            ->setDefinition(
                new InputDefinition(array(
                    new InputArgument("test_id", InputArgument::OPTIONAL, "Enter Suite to Test"),
                ))
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ghost = new Inspector;
        $client = $ghost->getClient();
        $testId = $input->getArgument("test_id");

        if ($testId) {
            $ghostIds = $ghost->getTest($testId);
        } else {
            $ghostIds = $ghost->getTests();
        }

        foreach($ghostIds as $ghostId) {
            $ghost->query($client, "tests/" . $ghostId["test_id"] . "/execute/");
            $data = $ghost->getUsefulData($ghost, $client, $ghostId);
            $summary = $ghost->getTestSummary($ghost, $client, $ghostId);

            foreach($summary as $behaviour){
                if(array_key_exists("error", $behaviour)) {
                    $email = $this->generateEmail($data, $ghostId, $summary);
                    break;
                }
            }
        }
    }

    function generateEmail($data, $ghostId, $summary)
    {
        $aws = new Factory;
        $doubleBreak = "\n" . "\n";
        $content = "";

        foreach ($summary as $step) {
            $keys = ["command", "target", "error"];

            foreach ($keys as $key) {
                if (array_key_exists($key, $step)) {
                    $content .= $key . ": " . $step[$key] . "\n";
                }  
            }

            $content .= "\n";
        }

        $aws->sendSesEmail($ghostId["email_address"], $data["testName"] . " report for " . date("d/m/Y"), "The test " . $data["testName"] . " in suite " . self::URL . "/suites/" . $data["testSuite"] . " has returned an error." . $doubleBreak . "Test Results:" . $doubleBreak . $content . "Viewable at: " . self::URL . "/tests/" . $data["testId"]);
    }
}

