<?php

declare(strict_types=1);

namespace ChoreQuest\Support;

class Str
{
    public static function normalisePath(string $path): string
    {
        $trimmed = '/' . ltrim($path, '/');
        $normalised = rtrim($trimmed, '/');

        return $normalised === '' ? '/' : $normalised;
    }
}
