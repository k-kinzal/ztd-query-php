<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Scalar;

/**
 * Prints an expression as the server prints it in messages: operators parenthesized, keywords in lower case.
 *
 * @visibility MySqlMemory
 */
final class Printer
{
    /**
     * Prints an expression.
     */
    public function expression(Scalar $node): string
    {
        return match (true) {
            $node instanceof Grouped => $this->expression($node->operand),
            $node instanceof Arithmetic => '(' . $this->expression($node->left) . ' ' . strtolower($node->operator->value) . ' ' . $this->expression($node->right) . ')',
            $node instanceof Unary => $node->operator->value . '(' . $this->expression($node->operand) . ')',
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall => strtolower($node->function->value) . '(' . implode(',', array_map(fn ($argument): string => $this->expression($argument), $node->arguments)) . ')',
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall => strtolower($node->name->value) . '(' . implode(',', array_map(fn ($argument): string => $this->expression($argument->expression), $node->arguments)) . ')',
            $node instanceof NumberLiteral => $node->text,
            $node instanceof StringLiteral => "'" . str_replace("'", "\\'", $node->value()) . "'",
            $node instanceof NullLiteral => 'NULL',
            $node instanceof ColumnUse => ($node->qualifier === null ? '' : '`' . $node->qualifier->name->value . '`.') . '`' . $node->name->value . '`',
            default => strtolower((new \ReflectionClass($node))->getShortName()),
        };
    }
}
