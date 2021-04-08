<?php

namespace App\Commands;

use Closure;
use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use League\Csv\Reader;
use League\Csv\Writer;
use RichJenks\Stats\Stats;

class YFP extends Command
{
    private const STEP = 0.62513;
    private const START = 404.33;
    private const RATIO_INTERVAL = [180, 220];

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'yfp';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Takes several file and calculate the fret';

    /**
     * Execute the console command.
     *
     * @param Filesystem $filesystem
     * @return mixed
     */
    public function handle(Filesystem $filesystem)
    {
        $numberOfFiles = 29;
        $numberOfRows = 512;
        $groups = [
            [
                'intensity' => 'low',
                'specterPrefix' => 'A',
                'backgroundPrefix' => 'B'
            ],
            [
                'intensity' => 'high',
                'specterPrefix' => 'C',
                'backgroundPrefix' => 'D'
            ]
        ];

        $this->task('Clean results directory', function () use ($filesystem) {
            $filesystem->deleteDirectory('results');
            $filesystem->makeDirectory('results');
        });

        $this->task('Generate subs files', function () use ($numberOfFiles, $groups) {
            foreach ($groups as $group) {
                $this->doubleIterationOverFiles(
                    $numberOfFiles,
                    'data/' . $group['specterPrefix'],
                    'data/' . $group['backgroundPrefix'],
                    'results/' . $group['intensity'] . '-sub-',
                    function ($recordA, $recordB) use ($group) {
                        $result = (float)$recordA[1] - (float)$recordB[1];

                        return [$result];
                    },
                    true,
                );
            }
        });

        $this->task('Join low intensity and high intensity', function () use ($numberOfFiles, $groups) {
            $this->doubleIterationOverFiles(
                $numberOfFiles,
                'results/low-sub-',
                'results/high-sub-',
                'results/low-and-high-sub-',
                function ($recordA, $recordB, $rowNumber) {
                    $position = self::START + ($rowNumber * self::STEP);

                    return [$position, $recordA[0], $recordB[0]];
                },
                true,
            );
        });

        $this->task('Generate A0', function () use ($numberOfFiles) {
            $this->doubleIterationOverFiles(
                $numberOfFiles,
                'results/low-sub-',
                'results/high-sub-',
                'results/A0-',
                function ($recordA, $recordB, $i, $fileNumber) {
                    $position = self::START + ($i * self::STEP);

                    if ($i < self::RATIO_INTERVAL[0] || $i > self::RATIO_INTERVAL[1]) {
                        return [$position, 0];
                    }

                    if ((float)$recordB[0] === 0.0) {
                        $row = $i + 1;
                        throw new Exception("Division by 0. File: {$fileNumber}, Row: {$row}, record low: {$recordA[0]}, record high: {$recordB[0]}");
                    }

                    return [$position, (float)($recordA[0]) / (float)($recordB[0])];
                }
            );
        });

        $this->task('Generate Mean of A0', function () use ($numberOfFiles, $numberOfRows) {
            $sums = array_fill(0, $numberOfRows, 0);
            $means = [];
            for ($n = 1; $n <= $numberOfFiles; ++$n) {
                $pathname = storage_path('results/A0-' . $n . '.csv');
                $records = Reader::createFromPath($pathname)->getRecords();

                $position = 0;
                foreach ($records as $record) {
                    $sums[$position] += (float)$record[1];

                    if ($n === $numberOfFiles - 1) {
                        $means[$position] = [$sums[$position] / $numberOfFiles];
                    }

                    $position++;
                }
            }

            $this->writeCsv('results/A0-means', $means);
        });

        $this->task('Calculate standard error', function () {
            $pathname = storage_path('results/A0-means.csv');
            $records = Reader::createFromPath($pathname)->getRecords();
            $result = [];

            foreach ($records as $record) {
                $result[] = $record[0];
            }

            $this->info("\nStandard error (mean) of A0 " . Stats::sem($result));
        });

        return 0;
    }

    public function doubleIterationOverFiles(
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

    private function writeCsv(string $resultPrefix, array $data)
    {
        $resultPathname = storage_path($resultPrefix . '.csv');
        if (File::exists($resultPathname)) {
            File::delete($resultPathname);
        }

        fopen($resultPathname, 'w');

        Writer::createFromPath($resultPathname)->insertAll($data);
    }
}
