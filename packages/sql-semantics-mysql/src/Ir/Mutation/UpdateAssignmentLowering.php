<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteralLowering;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves an assignment according to the native destination type.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UpdateAssignmentLowering
{
    /**
     * Allocates the write slot first, then resolves the current-row read.
     */
    public function lower(string $name, Scalar $expression, SourceBinding $binding): UpdateAssignment|TextUpdateAssignment
    {
        $target = $binding->updateField($name);
        if ($target instanceof TextColumnReference) {
            return new TextUpdateAssignment($target, $this->textValue($expression, $binding));
        }
        $value = (new ExpressionLowering())->lower($expression, $binding);
        Check::input($value instanceof ColumnReference || $value instanceof IntegerLiteral || $value instanceof NullLiteral, 'An UPDATE assignee requires a signed literal, NULL or a row reference.');

        return new UpdateAssignment($target, $value);
    }

    /**
     * Restricts the assignment to a native text field, text literal or NULL.
     */
    public function textValue(Scalar $expression, SourceBinding $binding): TextColumnReference|TextLiteral|NullLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        $value = match (true) {
            $expression instanceof ColumnUse => $binding->field($expression),
            $expression instanceof ParsedNull => new NullLiteral(),
            default => (new TextLiteralLowering())->lower($expression),
        };
        Check::input($value instanceof TextColumnReference || $value instanceof TextLiteral || $value instanceof NullLiteral, 'A VARCHAR UPDATE requires a text literal, NULL or a VARCHAR read slot.');

        return $value;
    }
}
