<?php

/**
 * @package Wellspring
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Wellspring;

enum CallbackType: string
{
    case Object = 'ob';
    case String = 'st';
    case ObjectArray = 'ao';
    case StringArray = 'as';
    case SerializedArray = 'ax';
    case SerializedFunction = 'fx';
}
