<?php

namespace Google\ExportData;

use AwsApi\Factory;
use Sql\Sql;
use Google\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\LockableTrait;

class SendInvoiceDataToSheetsTask extends Command
{
    use LockableTrait;

    const TAB_NAME      = "<TAB>";
    const SHEET_NAME    = "<SHEET>";

    protected function configure()
    {
        $this
            ->setName("google:export-data:send-invoice-data-to-sheets")
            ->setDescription("Sends CSV format data to google sheet daily")
            ->setDefinition(
                new InputDefinition(array(
                    new InputArgument("Type", InputArgument::REQUIRED, " "),
                ))
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln($this->getName() . " is already running in another process.");
            return 0;
        }
        $type = $input->getArgument("Type");

        $aws = new Factory;
        $sql = new Sql;
        $api = new Api;
        $sheetDetails = $api->getSheet(self::TAB_NAME, self::SHEET_NAME);

        $connection = $api->getClient();
        $service = new \Google_Service_Sheets($connection);
        $spreadsheetId = $service->spreadsheets->get($sheetDetails["sheet_id"])->getProperties();

        if ($type == "YTD" || $type == "YTD-FILL") {
            
            if ($type == "YTD") {
                $output->writeln("<comment>Deleting data table</comment>");
                $this->clearLogs();
                $api->deleteRange($service, $sheetDetails["sheet_id"], $sheetDetails["tab_name"] . "!A3:I9999");
            }

            $ytds = $this->getYTD();

            $output->writeln("<info>Adding table data for year to date</info>");
            foreach($ytds as $ytd) {

                if ($this->validateLog($ytd["Sales Invoice Date"], $ytd["Company"], $ytd["Customer Code"], $ytd["Invoice Type"])) {
                    continue;
                }

                if ($ytd["Customer Name"] == "") {
                    $ytd["Customer Name"] = "0";
                }

                if ($ytd["Sales Margin"] == "") {
                    $ytd["Sales Margin"] = "0";
                }

                if ($ytd["Convert Date"] == date("Y-m-d")) {
                    continue;
                }

                $values = [];
                foreach ($ytd as $cell) {
                    $values[0][] = $cell;
                }

                $values = $this->correctFormat($values);

                $api->appendCell($service, $sheetDetails["sheet_id"], $sheetDetails["tab_name"] . "!A:I", "RAW", "INSERT_ROWS", $values);

                $this->logInvoiced($ytd["Sales Invoice Date"], $ytd["Company"], $ytd["Customer Code"], $ytd["Invoice Type"]);

                sleep(2);
            }
        } else if ($type == "DAILY") { 
            $invoices = $this->getInvoiceData();

            $output->writeln("<info>Adding table data for dailies</info>");
            foreach($invoices as $invoice) {

                $values = [];
                foreach ($invoice as $cell) {
                    $values[0][] = $cell;
                }

                $values = $this->correctFormat($values);

                $api->appendCell($service, $sheetDetails["sheet_id"], $sheetDetails["tab_name"] . "!A:I", "RAW", "INSERT_ROWS", $values);
                sleep(2);
            }   
            $aws->sendSesEmail("<EMAIL>", "Daily Invoice Data for " . date("d/m/y"), "Daily Invoice report is complete, data can be viewed at:\n\n" . $sheetDetails["tab_url"]);
        } else {
            $output->writeln("<error>Incorrect type entered</error>");
        }
    }

    public function clearLogs() {
        $sql = new Sql;
        $mysql = $sql->getNewInstance("mysqlacc");
        $mysql->connect();
        $mysql->setTables([
            "table"    =>   "database" ,
        ]);

        $query = "DELETE FROM {table}";

        $mysql->query($query);
    }

    #Removed other functions
}

