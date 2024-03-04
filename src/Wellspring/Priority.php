<?php

/**
 * @package Veneer
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Wellspring;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function toInt(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 1,
            self::High => 2
        };
    }
}
