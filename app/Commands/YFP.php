<?php

namespace App\Commands;

use App\Services\Cleaner;
use App\Services\FretCommand;
use App\Services\StepCalculator;
use Exception;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use RichJenks\Stats\Stats;

class YFP extends FretCommand
{
    private const RATIO_INTERVAL = [237, 274];
    private const LASER_INTERVAL = [210, 557];
    private const RESULTS_DIR = 'results/yfp/';
    private const DATA_DIR = 'data/yfp/';
    private const GROUPS = [
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

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'yfp
        {--number-of-files=29 : Number of file to process}
        {--number-of-lines-by-file=512 : Number of lines of each file}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Takes several file and calculate the fret YFP';

    private array $highIntensityMax = [];

    /**
     * Execute the console command.
     *
     * @param StepCalculator $stepCalculator
     * @param Cleaner $cleaner
     * @return int
     */
    public function handle(StepCalculator $stepCalculator, Cleaner $cleaner)
    {
        $numberOfFiles = (int)$this->option('number-of-files');
        $numberOfRows = (int)$this->option('number-of-lines-by-file');

        $groups = self::GROUPS;

        $this->task('Clean results directory', function () use ($cleaner) {
            $cleaner->clean(self::RESULTS_DIR);
        });

        $this->task('Generate subs files', function () use ($numberOfFiles, $groups) {
            foreach ($groups as $group) {
                $this->doubleIterationOverFiles(
                    $numberOfFiles,
                    self::DATA_DIR . $group['specterPrefix'],
                    self::DATA_DIR . $group['backgroundPrefix'],
                    self::RESULTS_DIR . $group['intensity'] . '-sub-',
                    function ($recordA, $recordB) use ($group) {
                        $result = (float)$recordA[1] - (float)$recordB[1];

                        return [$result];
                    },
                    true,
                );
            }
        });

        $this->task('Join low intensity and high intensity', function () use ($numberOfFiles, $stepCalculator) {
            $this->doubleIterationOverFiles(
                $numberOfFiles,
                self::RESULTS_DIR . 'low-sub-',
                self::RESULTS_DIR . 'high-sub-',
                self::RESULTS_DIR . 'low-and-high-sub-',
                function ($recordA, $recordB, $rowNumber, $fileNumber) use ($stepCalculator) {
                    $position = $stepCalculator->calculate($rowNumber);
                    $result = $recordB[0];

                    if ($rowNumber >= self::LASER_INTERVAL[0] && $rowNumber <= self::LASER_INTERVAL[1]) {
                        if (!isset($this->highIntensityMax[$fileNumber])) {
                            $this->highIntensityMax[$fileNumber] = 0;
                        }

                        if ($this->highIntensityMax[$fileNumber] < $result) {
                            $this->highIntensityMax[$fileNumber] = $result;
                        }
                    }

                    return [$position, $recordA[0], $recordB[0]];
                },
                true,
            );
        });

        $this->info('The high intensity maximus are: ' . implode(', ', $this->highIntensityMax));

        $this->task('Max of high/low intensity', function () use ($numberOfFiles, $stepCalculator) {
            foreach (['high', 'low'] as $intensity) {
                $result = [];

                for ($n = 1; $n <= $numberOfFiles; ++$n) {
                    $i = $n - 1;
                    if (!isset($result[$i])) {
                        $result[$i] = [];
                    }

                    $pathname = Storage::path(self::RESULTS_DIR . $intensity . '-sub-' . $n . '.csv');
                    $records = Reader::createFromPath($pathname)->getRecords();

                    $j = 0;
                    foreach ($records as $record) {
                        $value = $record[0] / $this->highIntensityMax[$n];
                        $result[$j][$i] = $value;
                        ++$j;
                    }
                }

                $this->writeCsv(self::RESULTS_DIR . 'all-' . $intensity . '-intensity-normalized' . $n, $result);
            }
        });

        $this->task('Generate A0', function () use ($numberOfFiles, $stepCalculator) {
            $this->doubleIterationOverFiles(
                $numberOfFiles,
                self::RESULTS_DIR . 'low-sub-',
                self::RESULTS_DIR . 'high-sub-',
                self::RESULTS_DIR . 'A0-',
                function ($recordA, $recordB, $rowNumber, $fileNumber) use ($stepCalculator) {
                    $position = $stepCalculator->calculate($rowNumber);

                    if ($rowNumber < self::RATIO_INTERVAL[0] || $rowNumber > self::RATIO_INTERVAL[1]) {
                        return [$position, 0];
                    }

                    if ((float)$recordB[0] === 0.0) {
                        $this->newLine();
                        $this->error('May be you need a better adjust.');
                        $row = $rowNumber + 1;
                        throw new Exception("Division by 0. File: {$fileNumber}, Row: {$row}, record low: {$recordA[0]}, record high: {$recordB[0]}");
                    }

                    return [$position, (float)($recordA[0]) / (float)($recordB[0])];
                }
            );
        });

        $this->task('Join A0\'s', function () use ($numberOfFiles, $stepCalculator, $numberOfRows) {
            $data = [];

            for ($i = 0; $i < $numberOfRows; ++$i) {
                $data[$i] = [];
            }

            for ($i = 0; $i < $numberOfRows; ++$i) {
                $data[$i][0] = $stepCalculator;
            }

            for ($n = 1; $n <= $numberOfFiles; ++$n) {
                $pathname = Storage::path(self::RESULTS_DIR . 'A0-' . $n . '.csv');
                $records = Reader::createFromPath($pathname)->getRecords();

                $rowNumber = 0;
                $columnNumber = $n - 1;
                foreach ($records as $record) {
                    $data[$rowNumber][$columnNumber] = $record[1];
                    ++$rowNumber;
                }
            }

            $this->writeCsv(self::RESULTS_DIR . 'all-the-A0s', $data);
        });

        $this->task('Generate Mean of A0', function () use ($numberOfFiles, $numberOfRows) {
            $this->mean(
                $numberOfRows,
                $numberOfFiles,
                self::RESULTS_DIR . 'A0-',
                self::RESULTS_DIR . 'A0-means'
            );
        });

        $this->task('Calculate standard error', function () {
            $pathname = Storage::path(self::RESULTS_DIR . 'A0-means.csv');
            $records = Reader::createFromPath($pathname)->getRecords();
            $result = [];

            foreach ($records as $record) {
                $result[] = $record[0];
            }

            $this->info("\nStandard error (mean) of A0 " . Stats::sem($result));
        });

        return 0;
    }
}
