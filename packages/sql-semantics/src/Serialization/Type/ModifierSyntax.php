<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Type;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\NegatedParameter;
use SqlSemantics\Type\Modifier\TextParameter;

/**
 * Serializes the closed set of type-input operands without evaluating them.
 * @visibility SqlSemantics
 */
final class ModifierSyntax
{
    /**
     * Keeps identifier, literal, and negation boundaries explicit.
     */
    public static function write(NumericParameter|TextParameter|IdentifierParameter|NegatedParameter $parameter): Tree
    {
        return match (true) {
            $parameter instanceof NumericParameter => Build::keyword($parameter->spelling),
            $parameter instanceof TextParameter => Expressions::write($parameter->value),
            $parameter instanceof IdentifierParameter => Build::identifier([$parameter->name], Dialect::PostgreSql),
            $parameter instanceof NegatedParameter => new Tree('negated-type-parameter', [Build::keyword('-'), Build::parentheses(self::write($parameter->operand))]),
        };
    }
}
