<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Document\Xml;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Shared invariants of the PostgreSQL SQL/XML expressions.
 * @visibility SqlSemantics
 */
final class XmlInvariant
{
    /**
     * Requires PostgreSQL operands and a result of the named type.
     * @param list<Expression> $operands
     * @throws InvalidStructure
     */
    public static function check(string $form, ExpressionFacts $facts, string $type, array $operands): void
    {
        if ($facts->type->dialect !== Dialect::PostgreSql || $facts->type->name !== $type) {
            throw new InvalidStructure($form . ' is a PostgreSQL expression of type ' . $type . '.');
        }
        foreach ($operands as $operand) {
            if ($operand->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure($form . ' requires PostgreSQL operands.');
            }
        }
    }
}
