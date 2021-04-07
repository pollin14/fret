<?php

namespace App\Services;

use Illuminate\Support\Str;
use League\Csv\CannotInsertRecord;
use League\Csv\Writer;

class SaveCsv
{
    /**
     * @param array $positions
     * @param array $data
     * @param string $filename
     * @return string relative pathname to the storage
     * @throws CannotInsertRecord
     */
    public function __invoke(array $positions, array $data, string $filename): string
    {
        if (!Str::endsWith('.csv', $filename)) {
            $filename = $filename . '.csv';
        }

        $pathname = 'results/' . $filename;
        $writer = Writer::createFromPath(storage_path($pathname));

        $range = range(0, count($positions));
        foreach ($range as $index) {
            $writer->insertOne([$positions[$index], $data[$index]]);
        }

        return $pathname;
    }
}
