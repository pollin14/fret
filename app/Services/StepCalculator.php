<?php

namespace App\Services;

final class StepCalculator
{
    private const STEP = 0.57679;
    private const START = 380.41;

    public function calculate($row): float
    {
        return self::START + ($row * self::STEP);
    }
}
