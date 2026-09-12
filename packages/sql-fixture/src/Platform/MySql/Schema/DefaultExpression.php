<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Components\OptionsArray;
use UnexpectedValueException;

/**
 * Interprets a SQL default expression.
 *
 * @visibility root
 */
final class DefaultExpression
{
    /**
     * Interprets a DEFAULT clause while preserving SQL expressions.
     * @throws UnexpectedValueException
     */
    public function extractDefault(?OptionsArray $options): int|float|bool|string|null
    {
        if ($options === null || $options->has('DEFAULT') === false) {
            return null;
        }

        $optionsArray = $options->options;

        foreach ($optionsArray as $option) {
            if (!is_array($option)) {
                continue;
            }

            $name = $option['name'] ?? null;
            if ($name === 'DEFAULT') {
                $value = $option['value'] ?? null;
                if ($value === null) {
                    return null;
                }
                if (is_string($value)) {
                    if (preg_match('/^[\'"](.*)[\'"]\s*$/s', $value, $matches) === 1) {
                        return $matches[1];
                    }
                    if (strtoupper($value) === 'NULL') {
                        return null;
                    }
                    if (strtoupper($value) === 'TRUE') {
                        return true;
                    }
                    if (strtoupper($value) === 'FALSE') {
                        return false;
                    }
                    if (is_numeric($value)) {
                        if (str_contains($value, '.')) {
                            return (float) $value;
                        }
                        return (int) $value;
                    }
                    return $value;
                }
                if (!is_scalar($value)) {
                    throw new UnexpectedValueException('The SQL parser returned a non-scalar DEFAULT value.');
                }
                return $value;
            }
        }

        return null;
    }
}
