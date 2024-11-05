<?php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;

class GoogleSheetsService
{
    private $client;
    private $service;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig(storage_path('app/google-service-account.json'));
        $this->client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);
        $this->service = new Google_Service_Sheets($this->client);
    }

    public function getSheetData($spreadsheetId, $range)
    {
        return $this->service->spreadsheets_values->get($spreadsheetId, $range)->getValues();
    }
}
