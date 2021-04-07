<?php

namespace App\Services;

class Mean
{
    public function __invoke(array $data): float
    {
        return array_sum($data) / count($data);
    }
}
