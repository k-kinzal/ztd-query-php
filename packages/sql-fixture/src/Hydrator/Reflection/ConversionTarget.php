<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Reflection;

/**
 * PHP types for which database values have an explicit conversion rule.
 *
 * @visibility root
 */
enum ConversionTarget: string
{
    case Integer = 'int';
    case Float = 'float';
    case String = 'string';
    case Boolean = 'bool';
    case Array = 'array';
}
