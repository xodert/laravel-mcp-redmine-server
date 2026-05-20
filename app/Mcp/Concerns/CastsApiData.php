<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

trait CastsApiData
{
    private function strOf(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    private function intOf(mixed $value, int $default = 0): int
    {
        return is_scalar($value) ? (int) $value : $default;
    }

    private function floatOf(mixed $value, float $default = 0.0): float
    {
        return is_scalar($value) ? (float) $value : $default;
    }
}
