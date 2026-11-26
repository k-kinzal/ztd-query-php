<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Connection;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Connection\NameOperand;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;

/**
 * Lowers an SQLite connection operand in its source-free expression scope.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ConnectionOperands
{
    /**
     * Preserves the native root-identifier text conversion.
     */
    public function row(BoundStatement $operation, Scalar $expression): ValueRow
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        $value = $expression instanceof NameOperand
            ? new TextLiteral($expression->name->value)
            : (new ExpressionLowering())->lower($expression, new SourceBinding($operation, null));

        return new ValueRow([$value]);
    }

    /**
     * Separates a native constant name from an explicit runtime parameter.
     * @throws InvalidConstruction When the checked expression does not establish a name role
     */
    public function name(BoundStatement $operation, Scalar $expression): AttachmentName|Parameter
    {
        $value = $this->row($operation, $expression)->values[0];
        if ($value instanceof Parameter) {
            return $value;
        }
        Check::input(
            $value instanceof TextLiteral || $value instanceof IntegerLiteral || $value instanceof NullLiteral,
            'An SQLite connection name requires a checked constant or numbered parameter.'
        );

        return new AttachmentName($value);
    }
}
