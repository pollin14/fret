<?php

namespace App\Services;

final class StepCalculator
{
    private const STEP = 0.39108;
    private const START = 328.06;

    public function calculate($row): float
    {
        return self::START + ($row * self::STEP);
    }
}
