<?php

namespace App\Services;

final class StepCalculator
{
    private const STEP = 0.62513;
    private const START = 404.33;

    public function calculate($row): float
    {
        return self::START + ($row * self::STEP);
    }
}
