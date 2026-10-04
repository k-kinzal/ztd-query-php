<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Answers the type an output field has before PostgreSQL resolves untyped literals to `text`.
 *
 * Rule: PG-UNKNOWN-OUTPUT-001. A select list resolves an untyped string
 * constant or NULL to `text`, except where its consumer resolves it: the
 * operands of a set operation and the rows of INSERT … SELECT keep the
 * pseudo-type `unknown` so that the common type or the target column decides.
 * Source: https://www.postgresql.org/docs/17/typeconv-select.html,
 * https://www.postgresql.org/docs/17/typeconv-union-case.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class UnknownOutputs
{
    /**
     * Answers the type of a field as its consumer sees it.
     */
    public function raw(Field $field): TypeFact
    {
        $expression = $field->expression;
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof NullLiteral || ($expression instanceof Constant && $expression->value instanceof StringConstant)) {
            return new Known(Builtin::Unknown);
        }

        return $field->type;
    }
}
