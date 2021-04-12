<?php

namespace App\Commands;

use App\Services\Cleaner;
use App\Services\FretCommand;
use App\Services\StepCalculator;
use Exception;
use League\Csv\Reader;
use RichJenks\Stats\Stats;

class YFP extends FretCommand
{
    private const RATIO_INTERVAL = [180, 220];
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

    /**
     * Execute the console command.
     *
     * @param StepCalculator $stepCalculator
     * @param Cleaner $cleaner
     * @return int
     */
    public function handle(StepCalculator $stepCalculator, Cleaner $cleaner)
    {
        $numberOfFiles = $this->option('number-of-files');
        $numberOfRows = $this->option('number-of-lines-by-file');

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
                function ($recordA, $recordB, $rowNumber) use ($stepCalculator) {
                    $position = $stepCalculator->calculate($rowNumber);

                    return [$position, $recordA[0], $recordB[0]];
                },
                true,
            );
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

        $this->task('Generate Mean of A0', function () use ($numberOfFiles, $numberOfRows) {
            $this->mean(
                $numberOfRows,
                $numberOfFiles,
                self::RESULTS_DIR . 'A0-',
                self::RESULTS_DIR . 'A0-means'
            );
        });

        $this->task('Calculate standard error', function () {
            $pathname = storage_path(self::RESULTS_DIR . 'A0-means.csv');
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
