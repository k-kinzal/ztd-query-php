<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Statement\Node;

/**
 * Tells which strings MySQL 5.6 and 5.7 read as a number without warning when more than a number is written in them.
 *
 * Those releases warn (ER_TRUNCATED_WRONG_VALUE) only when they read a number from a string
 * literal, a column, a system variable or VERSION(), also through parentheses and unary plus, or from an IF, NULLIF, ELT, GREATEST, LEAST,
 * CASE or scalar subquery that passes such a string on; the string a function computes, a user
 * variable, COLLATE and BINARY read silently, COALESCE and IFNULL included (verified on live
 * 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/5.7/en/type-conversion.html.
 *
 * @visibility MySqlMemory
 */
final class Quietness
{
    /**
     * The functions that pass the number of an argument on.
     */
    public const PASSING = ['IF', 'NULLIF', 'ELT', 'GREATEST', 'LEAST'];

    /**
     * Tells whether the string an expression answers reads as a number with a warning.
     */
    public function loud(Node $node): bool
    {
        $node = $this->bare($node);
        if ($node instanceof StringLiteral || $node instanceof RadixLiteral || $node instanceof ColumnUse || $node instanceof SystemVariable) {
            return true;
        }
        if ($node instanceof CaseExpression) {
            return array_filter([...array_map(static fn ($branch): Node => $branch->result, $node->branches), ...($node->else === null ? [] : [$node->else])], $this->loud(...)) !== [];
        }
        if ($node instanceof ScalarSubquery) {
            $query = $node->query;

            return !$query instanceof Select || !($query->items[0] ?? null) instanceof SelectExpression || $this->loud($query->items[0]->expression);
        }
        if ($node instanceof KeywordCall) {
            return $node->function === KeywordFunction::If && array_filter(array_slice($node->arguments, 1), $this->loud(...)) !== [];
        }
        if ($node instanceof FunctionCall && $node->schema === null) {
            $name = strtoupper($node->name->value);

            return $name === 'VERSION' || (in_array($name, self::PASSING, true) && array_filter($node->arguments, fn (CallArgument $argument): bool => $this->loud($argument->expression)) !== []);
        }

        return false;
    }

    /**
     * Answers the expression written inside parentheses and unary plus, which pass its string on unchanged.
     */
    public function bare(Node $node): Node
    {
        while ($node instanceof Grouped || ($node instanceof Unary && $node->operator === UnaryOperator::Plus)) {
            $node = $node->operand;
        }

        return $node;
    }
}
