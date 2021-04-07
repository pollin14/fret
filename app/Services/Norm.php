<?php

namespace App\Services;

class Norm
{
    public function __invoke(array $data): array
    {
        $max = max($data);

        return array_map(function ($item) use ($max) {
            return $item / $max;
        }, $data);
    }
}
