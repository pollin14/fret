<?php

namespace App\Commands;

use App\Services\CalculatePosition;
use App\Services\Mean;
use App\Services\Norm;
use App\Services\SaveCsv;
use App\Services\Sub;
use LaravelZero\Framework\Commands\Command;

class Compute extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'fret:compute';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Takes several file and calculate the fret';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->task('Calculate', function () {
            $numberOfFiles = 30;
            $fileNumbers = range(1, $numberOfFiles);

            $means = [];
            $positions = [];
            foreach ($fileNumbers as $n) {
                $sub = $this->app->make(Sub::class)($n);

                $normalized = $this->app->make(Norm::class)($sub);

                $means[] = $this->app->make(Mean::class)($normalized);

                $positions[] = $this->app->make(CalculatePosition::class);
            }

            $pathname = $this->app->make(SaveCsv::class)($positions, $means);

            $this->info('The resulting file was generated in: ' . $pathname);
        });

        return 0;
    }
}
