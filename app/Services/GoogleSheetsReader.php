<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use RuntimeException;
use Throwable;

class GoogleSheetsReader
{
    public function values(string $spreadsheetId, string $range): array
    {
        $credentialsPath = config('services.google_sheets.credentials_path');

        if (! $credentialsPath || ! is_file($credentialsPath)) {
            throw new RuntimeException('Set GOOGLE_APPLICATION_CREDENTIALS to the service-account JSON file.');
        }

        try {
            $client = new Client;
            $client->setApplicationName('ClassDesk');
            $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
            $client->setAuthConfig($credentialsPath);

            $sheets = new Sheets($client);

            return $sheets->spreadsheets_values->get($spreadsheetId, $range)->getValues() ?? [];
        } catch (Throwable $exception) {
            throw new RuntimeException('The Google Sheets request failed.', previous: $exception);
        }
    }
}
