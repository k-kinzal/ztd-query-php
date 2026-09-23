<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Modifier;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity;

/**
 * Restricts type-input operand forms to their database language.
 * @visibility SqlSemantics
 */
final class ParameterInvariant
{
    /**
     * PostgreSQL accepts classified literal and identifier inputs; other dialects require numeric sizes.
     * @throws InvalidStructure
     */
    public static function dialect(Dialect $dialect, Identity\TypeIdentity $identity): void
    {
        if ($identity instanceof Identity\StringStorage && $identity->national && $dialect !== Dialect::MySql) {
            throw new InvalidStructure('National character-set selection requires MySQL.');
        }
        if ($dialect === Dialect::PostgreSql) {
            return;
        }
        $parameters = match (true) {
            $identity instanceof Identity\Numeric\NumericStorage => [$identity->precision, $identity->scale],
            $identity instanceof Identity\StringStorage => [$identity->length],
            $identity instanceof Identity\TemporalStorage => [$identity->precision],
            default => [],
        };
        foreach ($parameters as $parameter) {
            if ($parameter !== null && !$parameter instanceof Identity\Numeric\NumericParameter) {
                throw new InvalidStructure('This type-modifier operand requires PostgreSQL.');
            }
        }
    }
}
