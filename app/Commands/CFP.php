<?php

namespace App\Commands;

use App\Services\Cleaner;
use App\Services\FretCommand;
use App\Services\StepCalculator;
use League\Csv\Reader;

class CFP extends FretCommand
{
    const RESULTS_DIR = 'results/cfp/';
    const DATA_DIR = 'data/cfp/';

    /**
     * @var string
     */
    protected $signature = 'cfp
        {--number-of-files=29 : Number of file to process}
        {--number-of-lines-by-file=512 : Number of lines of each file}';

    /**
     * @var string
     */
    protected $description = 'Takes several file and calculate the fret CPF';
    /**
     * @var array
     */
    private array $max = [];

    /**
     * Execute the console command.
     *
     * @param Cleaner $cleaner
     * @param StepCalculator $stepCalculator
     * @return int
     */
    public function handle(Cleaner $cleaner, StepCalculator $stepCalculator)
    {
        $numberOfFiles = $this->option('number-of-files');
        $numberOfRows = $this->option('number-of-lines-by-file');

        $this->task('Clean results directory', function () use ($cleaner) {
            $cleaner->clean(self::RESULTS_DIR);
        });

        $this->task('Generate subs files', function () use ($numberOfFiles) {
            $this->doubleIterationOverFiles(
                $numberOfFiles,
                self::DATA_DIR . 'A',
                self::DATA_DIR . 'B',
                self::RESULTS_DIR . 'sub-',
                function ($recordA, $recordB, $row, $fileNumber) {
                    $result = (float)$recordA[1] - (float)$recordB[1];

                    if (!isset($this->max[$fileNumber])) {
                        $this->max[$fileNumber] = 0;
                    }

                    if ($this->max[$fileNumber] < $result) {
                        $this->max[$fileNumber] = $result;
                    }

                    return [$result];
                },
                true,
            );
        });

        $this->task('Generate normalized files', function () use ($numberOfFiles, $stepCalculator) {
            for ($n = 1; $n <= $numberOfFiles; ++$n) {
                $pathname = storage_path(self::RESULTS_DIR . 'sub-' . $n . '.csv');
                $records = Reader::createFromPath($pathname)->getRecords();

                $row = 0;
                $result = [];
                foreach ($records as $record) {
                    $value = $record[0] / $this->max[$n];
                    $position = $stepCalculator->calculate($row);
                    $result[] = [$position, $value];

                    $row++;
                }

                $this->writeCsv(self::RESULTS_DIR . 'normalized-' . $n, $result);
            }
        });

        $this->task('Generate mean of normalized files', function () use ($numberOfFiles, $numberOfRows) {
            $this->mean(
                $numberOfRows,
                $numberOfFiles,
                self::RESULTS_DIR . 'normalized-',
                self::RESULTS_DIR . 'means',
            );
        });

        return 0;
    }
}
