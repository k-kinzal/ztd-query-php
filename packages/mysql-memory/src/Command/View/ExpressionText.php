<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Dictionary\Routine;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Writes the expressions of the query of a view as the server stores them and SHOW CREATE VIEW
 * shows them.
 *
 * Function names and keywords are in lower case; each column is qualified by the table it is
 * read from; an operation is enclosed in parentheses; COUNT(*) is count(0), a negative number
 * -(n), a negated comparison the opposite comparison, and NOT of a value a comparison of 0 with
 * it. An expression the writer does not know answers null (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-view.html.
 *
 * @visibility MySqlMemory
 */
final class ExpressionText
{
    /**
     * @param ViewText $text The writer of the query the expressions are in, which writes their columns and subqueries
     */
    public function __construct(public readonly ViewText $text)
    {
    }

    /**
     * Writes a list of expressions, or answers null when one cannot be written.
     *
     * @param list<Scalar> $scalars
     * @return list<string>|null
     */
    public function scalars(array $scalars): ?array
    {
        $written = [];
        foreach ($scalars as $scalar) {
            $text = $this->scalar($scalar);
            if ($text === null) {
                return null;
            }
            $written[] = $text;
        }

        return $written;
    }

    /**
     * Writes an expression.
     */
    public function scalar(Scalar $scalar): ?string
    {
        return match (true) {
            $scalar instanceof ColumnUse => $this->text->facts->covers($scalar) ? $this->text->column($this->text->facts->scalar($scalar)->resolution) : null,
            $scalar instanceof Grouped => $this->scalar($scalar->operand),
            $scalar instanceof NumberLiteral, $scalar instanceof SignedLiteral, $scalar instanceof StringLiteral, $scalar instanceof NullLiteral, $scalar instanceof BooleanLiteral => $this->literal($scalar),
            $scalar instanceof FunctionCall => $this->call(strtolower($scalar->name->value), array_map(static fn ($argument): Scalar => $argument->expression, $scalar->arguments), $scalar->schema === null ? '' : Routine::quoted($scalar->schema->value) . '.'),
            $scalar instanceof Aggregate => $this->aggregate($scalar),
            $scalar instanceof CaseExpression => $this->branches($scalar),
            $scalar instanceof ScalarSubquery => $this->subquery('(', $scalar->query, ')'),
            $scalar instanceof Exists => $this->subquery('exists(', $scalar->query, ')'),
            $scalar instanceof InQuery => $this->inQuery($scalar),
            default => $this->operator($scalar),
        };
    }

    /**
     * Writes a literal: a negative number as -(n), and a string with its introducer and its
     * backslashes and quotes escaped.
     */
    public function literal(Scalar $literal): ?string
    {
        return match (true) {
            $literal instanceof NumberLiteral => $literal->text,
            $literal instanceof SignedLiteral => $literal->negative ? '-(' . $literal->number->text . ')' : $literal->number->text,
            $literal instanceof StringLiteral => ($literal->introducer === null ? '' : '_' . strtolower($literal->introducer->value)) . "'" . strtr($literal->value(), ['\\' => '\\\\', "'" => "\\'"]) . "'",
            $literal instanceof NullLiteral => 'NULL',
            $literal instanceof BooleanLiteral => $literal->value ? 'true' : 'false',
            default => null,
        };
    }

    /**
     * Writes an operator expression or a predicate, or answers null for an expression the writer
     * does not know.
     */
    public function operator(Scalar $scalar): ?string
    {
        return match (true) {
            $scalar instanceof Comparison => $this->binary($scalar->left, $scalar->operator->value, $scalar->right),
            $scalar instanceof Arithmetic => $this->binary($scalar->left, $scalar->operator->value, $scalar->right),
            $scalar instanceof Logical => $this->binary($scalar->left, strtolower($scalar->operator->value), $scalar->right),
            $scalar instanceof Collated => $this->wrap($scalar->operand, '(', ' collate ' . $scalar->collation->value . ')'),
            $scalar instanceof NullTest => $this->wrap($scalar->operand, '(', $scalar->negated ? ' is not null)' : ' is null)'),
            $scalar instanceof Not => $this->not($scalar->operand),
            $scalar instanceof Unary => $this->unary($scalar),
            $scalar instanceof Between => $this->between($scalar),
            $scalar instanceof InList => $this->in($scalar),
            $scalar instanceof Like => $this->like($scalar),
            default => null,
        };
    }

    /**
     * Writes a unary operator: + as its operand alone, ! as a negation, and another before its
     * operand in parentheses.
     */
    public function unary(Unary $unary): ?string
    {
        if ($unary->operator === UnaryOperator::Plus) {
            return $this->scalar($unary->operand);
        }

        return $unary->operator === UnaryOperator::Not ? $this->not($unary->operand) : $this->wrap($unary->operand, $unary->operator->value . '(', ')');
    }

    /**
     * Writes LIKE, NOT LIKE as the negation of LIKE; LIKE with ESCAPE is not written.
     */
    public function like(Like $like): ?string
    {
        if ($like->escape !== null) {
            return null;
        }

        return $like->negated ? $this->wrap(new Like($like->operand, $like->pattern), '(not(', '))') : $this->binary($like->operand, 'like', $like->pattern);
    }

    /**
     * Writes a binary operation in parentheses.
     */
    public function binary(Scalar $left, string $operator, Scalar $right): ?string
    {
        $first = $this->scalar($left);
        $second = $this->scalar($right);

        return $first === null || $second === null ? null : '(' . $first . ' ' . $operator . ' ' . $second . ')';
    }

    /**
     * Writes an expression between a prefix and a suffix.
     */
    public function wrap(Scalar $operand, string $before, string $after, bool $refused = false): ?string
    {
        $text = $this->scalar($operand);

        return $text === null || $refused ? null : $before . $text . $after;
    }

    /**
     * Writes a negation: a comparison negated, or a comparison of the operand with 0.
     */
    public function not(Scalar $operand): ?string
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        $negated = $operand instanceof Comparison ? match ($operand->operator) {
            ComparisonOperator::Equal => '<>',
            ComparisonOperator::NotEqual => '=',
            ComparisonOperator::Less => '>=',
            ComparisonOperator::LessOrEqual => '>',
            ComparisonOperator::Greater => '<=',
            ComparisonOperator::GreaterOrEqual => '<',
            ComparisonOperator::NullSafeEqual => null,
        } : null;
        if ($operand instanceof Comparison && $negated !== null) {
            return $this->binary($operand->left, $negated, $operand->right);
        }
        if ($operand instanceof NullTest) {
            return $this->wrap($operand->operand, '(', $operand->negated ? ' is null)' : ' is not null)');
        }
        if ($operand instanceof ColumnUse || $operand instanceof Arithmetic || $operand instanceof NumberLiteral || $operand instanceof FunctionCall) {
            return $this->wrap($operand, '(0 = ', ')');
        }
        $text = $this->scalar($operand);

        return $text === null ? null : '(not(' . $text . '))';
    }

    /**
     * Writes BETWEEN.
     */
    public function between(Between $between): ?string
    {
        $operand = $this->scalar($between->operand);
        $low = $this->scalar($between->low);
        $high = $this->scalar($between->high);

        return $operand === null || $low === null || $high === null ? null : '(' . $operand . ($between->negated ? ' not between ' : ' between ') . $low . ' and ' . $high . ')';
    }

    /**
     * Writes IN over a list; over one element, it is a comparison with it.
     */
    public function in(InList $in): ?string
    {
        if (count($in->elements) === 1) {
            return $this->binary($in->operand, $in->negated ? '<>' : '=', $in->elements[0]);
        }
        $operand = $this->scalar($in->operand);
        $elements = $this->scalars($in->elements);

        return $operand === null || $elements === null ? null : '(' . $operand . ($in->negated ? ' not in (' : ' in (') . implode(',', $elements) . '))';
    }

    /**
     * Writes IN over a subquery, the subquery without the names of its select items.
     */
    public function inQuery(InQuery $in): ?string
    {
        return $this->wrap($in->operand, '', ($in->negated ? ' not in (' : ' in (') . $this->text->query($in->query, false) . ')', $this->text->query($in->query, false) === null);
    }

    /**
     * Writes a function call with its arguments.
     *
     * @param list<Scalar> $arguments
     */
    public function call(string $name, array $arguments, string $schema = ''): ?string
    {
        $written = $this->scalars($arguments);

        return $written === null ? null : $schema . $name . '(' . implode(',', $written) . ')';
    }

    /**
     * Writes an aggregate: COUNT(*) as count(0).
     */
    public function aggregate(Aggregate $aggregate): ?string
    {
        if ($aggregate->over !== null || $aggregate->function === AggregateFunction::JsonArray || $aggregate->function === AggregateFunction::Collect) {
            return null;
        }
        $name = strtolower($aggregate->function->value);
        $arguments = $aggregate->arguments;
        if ($arguments === []) {
            return $name . '(0)';
        }
        $written = $this->scalars($arguments);

        return $written === null ? null : $name . '(' . ($aggregate->distinct ? 'distinct ' : '') . implode(',', $written) . ')';
    }

    /**
     * Writes a CASE expression.
     */
    public function branches(CaseExpression $case): ?string
    {
        $text = '(case';
        if ($case->operand !== null) {
            $operand = $this->scalar($case->operand);
            if ($operand === null) {
                return null;
            }
            $text .= ' ' . $operand;
        }
        foreach ($case->branches as $branch) {
            $condition = $this->scalar($branch->condition);
            $result = $this->scalar($branch->result);
            if ($condition === null || $result === null) {
                return null;
            }
            $text .= ' when ' . $condition . ' then ' . $result;
        }
        if ($case->else !== null) {
            $else = $this->scalar($case->else);
            if ($else === null) {
                return null;
            }
            $text .= ' else ' . $else;
        }

        return $text . ' end)';
    }

    /**
     * Writes a subquery between a prefix and a suffix, without the names of its select items.
     */
    public function subquery(string $before, Query $query, string $after): ?string
    {
        $text = $this->text->query($query, false);

        return $text === null ? null : $before . $text . $after;
    }
}
