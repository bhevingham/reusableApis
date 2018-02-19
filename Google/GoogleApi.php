<?php

namespace Google;

use Sql\Sql;

class Api
{


    function initializeAnalytics()
    {
        $client = new \Google_Client();
        $client->setApplicationName("<APP NAME>");
        $client->setAuthConfig(__DIR__);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        $analytics = new \Google_Service_Analytics($client);
        return $analytics;
    }


    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function getClient()
    {

        define("APPLICATION_NAME", "Google Sheets API PHP Quickstart");
        define("CREDENTIALS_PATH", "~/.credentials/sheets.googleapis.com-php-quickstart.json");
        define("CLIENT_SECRET_PATH", __DIR__ . "/client_connection_detail.json");
        // If modifying these scopes, delete your previously saved credentials
        // at ~/.credentials/sheets.googleapis.com-php-quickstart.json
        define("SCOPES", implode(" ", array(
          \Google_Service_Sheets::SPREADSHEETS)
        ));

      $client = new \Google_Client();
      $client->setApplicationName(APPLICATION_NAME);
      $client->setScopes(SCOPES);
      $client->setAuthConfig(CLIENT_SECRET_PATH);
      $client->setAccessType("offline");

      // Load previously authorized credentials from a file.
      $credentialsPath = $this->expandHomeDirectory(CREDENTIALS_PATH);
      if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
      } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print "Enter verification code: ";
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if(!file_exists(dirname($credentialsPath))) {
          mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
      }

      $client->setAccessToken($accessToken);

      // Refresh the token if it's expired.
      if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
      }
      return $client;
    }

    /**
     * Expands the home directory alias '~' to the full path.
     * @param string $path the path to expand.
     * @return string the expanded path.
     */
    function expandHomeDirectory($path) {
        $homeDirectory = getenv("HOME");
        if (empty($homeDirectory)) {
            $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
        }
        return str_replace("~", realpath($homeDirectory), $path);
    }

    function appendCell ($service, $spreadsheetId, $range, $valueInputOption, $insertDataOption, $values) 
    {
      $spreadsheetId = $spreadsheetId;  

      $optParams = [];

      $optParams["valueInputOption"] = $valueInputOption;  

      $optParams["insertDataOption"] = $insertDataOption;  

      $requestBody = new \Google_Service_Sheets_ValueRange([
          "values"  =>  $values,
      ]);

      $response = $service->spreadsheets_values->append($spreadsheetId, $range, $requestBody, $optParams);
    }

    function deleteRange ($service, $spreadsheetId, $range)
    {
      $spreadsheetId = $spreadsheetId;  

      $requestBody = new \Google_Service_Sheets_ClearValuesRequest();

      $response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

      // TODO: Change code below to process the `response` object:
      echo "<pre>", var_export($response, true), "</pre>", "\n";
    }

    public function getSheet($tabName, $sheetName)
    {
        $sql = new Sql;
        $websql = $sql->getNewInstance("webteam");
        $websql->connect();
        $websql->setTables([
          "google_sheets"         =>  "reporting",
          "google_sheets_tabs"    =>  "reporting",
        ]);

        $query = "SELECT a.tab_name, b.sheet_id, tab_url FROM {google_sheets_tabs} AS a
                  JOIN {google_sheets} AS b ON a.sheet_id = b.id
                  WHERE tab_name = ?tabName
                  AND sheet_name = ?sheetName";

        $params = [
          "tabName"     =>  $tabName,
          "sheetName"   =>  $sheetName,
        ];

        return $websql->fetchSingle($query, $params);
    }

}
