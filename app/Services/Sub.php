<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use League\Csv\Reader;
use League\Flysystem\FileNotFoundException;

class Sub
{
    private const DATA_COLUMN = 1;
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param int $index
     * @return array
     * @throws FileNotFoundException
     */
    public function __invoke(int $index): array
    {
        $this->checkFile($index);

        $readerA = Reader::createFromPath(storage_path("A{$index}"));
        $readerB = Reader::createFromPath(storage_path("B{$index}"));

        $recordsA = $readerA->getRecords();
        $recordsB = $readerB->getRecords();

        $result = [];
        foreach ($recordsA as $recordA) {
            foreach ($recordsB as $recordB) {
                $result[] = (float)$recordA[self::DATA_COLUMN] - (float)$recordB[self::DATA_COLUMN];
            }
        }

        return $result;
    }

    private function checkFile(int $index)
    {
        if (!$this->filesystem->exists(storage_path('A' . $index))) {
            throw new FileNotFoundException(storage_path('A' . $index));
        }

        if (!$this->filesystem->exists(storage_path('B' . $index))) {
            throw new FileNotFoundException(storage_path('B' . $index));
        }
    }
}
