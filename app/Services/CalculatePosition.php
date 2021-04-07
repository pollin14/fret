<?php

namespace App\Services;

class CalculatePosition
{
    private const DELTA = 0.62513;
    private const START = 404.33;

    /**
     * @param int $index
     * @return float
     */
    public function __invoke(int $index): float
    {
        $step = self::DELTA * ($index - 1);

        return self::START + $step;
    }
}
