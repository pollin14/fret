<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;

class Cleaner
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function clean(string $dir)
    {
        $this->filesystem->deleteDirectory($dir);
        $this->filesystem->makeDirectory($dir);
    }
}
