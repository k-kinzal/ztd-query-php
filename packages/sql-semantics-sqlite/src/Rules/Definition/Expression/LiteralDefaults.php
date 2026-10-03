<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Expression;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Statement\Scalar;

/**
 * Reads the DEFAULT clauses of a column the way ALTER TABLE ADD COLUMN does.
 *
 * Rule: SQLITE-LITERAL-DEFAULT-001. When a column is added, SQLite computes
 * its default once, with the routine that turns a literal expression into a
 * value: it looks through parentheses, a unary plus or minus and a CAST, and
 * accepts a number, a string, a BLOB, NULL, TRUE, FALSE and the identifier
 * after DEFAULT; everything else, CURRENT_TIME, CURRENT_DATE and
 * CURRENT_TIMESTAMP included, is "a column with non-constant default". A
 * default written as the literal NULL, in parentheses or not, is read as no
 * default at all. Terminates: each step removes one wrapper of a finite
 * expression.
 * Source: https://sqlite.org/lang_altertable.html#alter_table_add_column
 * (and `sqlite3AlterFinishAddColumn()` in alter.c and `valueFromExpr()` in
 * vdbemem.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class LiteralDefaults
{
    /**
     * Answers the DEFAULT clauses of a column in written order.
     *
     * @param list<ColumnConstraint> $constraints
     * @return list<DefaultLiteral|DefaultExpression|DefaultWord>
     */
    public function defaults(array $constraints): array
    {
        $defaults = [];
        foreach ($constraints as $constraint) {
            if ($constraint instanceof DefaultLiteral || $constraint instanceof DefaultExpression || $constraint instanceof DefaultWord) {
                $defaults[] = $constraint;
            }
        }

        return $defaults;
    }

    /**
     * Tells whether a DEFAULT clause is the literal NULL that SQLite reads as no default.
     */
    public function null(DefaultLiteral|DefaultExpression|DefaultWord $default): bool
    {
        if ($default instanceof DefaultLiteral) {
            return $default->sign === null && $default->literal instanceof NullLiteral;
        }

        return $default instanceof DefaultExpression && $this->core($default->expression) instanceof NullLiteral;
    }

    /**
     * Tells whether SQLite computes a value from a DEFAULT clause when the column is added.
     */
    public function literal(DefaultLiteral|DefaultExpression|DefaultWord $default): bool
    {
        if ($default instanceof DefaultLiteral) {
            return !$default->literal instanceof CurrentTime;
        }

        return $default instanceof DefaultWord || $this->constant($default->expression);
    }

    /**
     * Tells whether an expression is a literal under parentheses, signs and casts.
     */
    public function constant(Scalar $expression): bool
    {
        while (true) {
            if ($expression instanceof Grouped || $expression instanceof Cast) {
                $expression = $expression->operand;
            } elseif ($expression instanceof Unary && ($expression->operator === UnaryOperator::Plus || $expression->operator === UnaryOperator::Minus)) {
                $expression = $expression->operand;
            } else {
                break;
            }
        }

        return $expression instanceof TruthWord || (!$expression instanceof CurrentTime && str_starts_with($expression::class, 'SqlSemantics\\Platform\\Sqlite\\Statement\\Expression\\Literal\\'));
    }

    /**
     * Answers an expression without its parentheses.
     */
    public function core(Scalar $expression): Scalar
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression;
    }
}
