<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use League\Csv\Reader;
use League\Csv\Writer;

class FretCommand extends Command
{
    protected function doubleIterationOverFiles(
        int $numberOfFiles,
        string $prefixA,
        string $prefixB,
        string $resultPrefix,
        Closure $callback,
        bool $skipHeader = false
    )
    {
        for ($n = 1; $n <= $numberOfFiles; ++$n) {
            $pathnameA = storage_path($prefixA . $n . '.csv');
            $pathnameB = storage_path($prefixB . $n . '.csv');

            $recordsA = Reader::createFromPath($pathnameA)->getRecords();
            $recordsB = Reader::createFromPath($pathnameB)->getRecords();

            $recordsA->rewind();
            $recordsB->rewind();
            $result = [];
            $recordNumber = 0;
            $headerSkipped = false;
            while ($recordsA->valid() && $recordsB->valid()) {
                if ($skipHeader && !$headerSkipped) {
                    $headerSkipped = true;

                    $recordsA->next();
                    $recordsB->next();

                    continue;
                }
                $recordA = $recordsA->current();
                $recordB = $recordsB->current();

                $result[] = $callback($recordA, $recordB, $recordNumber, $n);

                $recordsA->next();
                $recordsB->next();
                $recordNumber++;
            }

            $this->writeCsv($resultPrefix . $n, $result);
        }
    }

    protected function writeCsv(string $resultPrefix, array $data)
    {
        $resultPathname = storage_path($resultPrefix . '.csv');
        if (File::exists($resultPathname)) {
            File::delete($resultPathname);
        }

        fopen($resultPathname, 'w');

        Writer::createFromPath($resultPathname)->insertAll($data);
    }

    protected function mean(int $numberOfRows, int $numberOfFiles, string $dataPrefix, string $resultPrefix)
    {
        $sums = array_fill(0, $numberOfRows, 0);
        $means = [];
        for ($n = 1; $n <= $numberOfFiles; ++$n) {
            $pathname = storage_path($dataPrefix . $n . '.csv');
            $records = Reader::createFromPath($pathname)->getRecords();

            $position = 0;
            foreach ($records as $record) {
                $sums[$position] += (float)$record[1];

                if ($n === $numberOfFiles - 1) {
                    $step = $this->app->make(StepCalculator::class)->calculate($position);
                    $means[$position] = [$step, $sums[$position] / $numberOfFiles];
                }

                $position++;
            }
        }

        $this->writeCsv($resultPrefix, $means);
    }
}
