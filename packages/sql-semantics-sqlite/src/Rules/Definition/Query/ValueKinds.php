<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition\Query;

use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;

/**
 * Derives the set of storage classes SQLite expects a result expression of a compound arm to produce.
 *
 * Rule: SQLITE-DATA-TYPE-001. When the affinity of a column of a compound
 * query is decided, SQLite asks every other arm which kinds of value its
 * expression can produce, as a set of three bits: numeric (1), text (2) and
 * BLOB (4). A NULL produces nothing; a string literal, a concatenation and a
 * double-quoted word that is no column produce text; a BLOB literal
 * produces a BLOB; a bound parameter and a function call produce anything; a
 * column, a subquery, a CAST and a row value produce numbers and BLOBs
 * (numeric affinity), text and BLOBs (TEXT affinity), or anything (no
 * affinity or BLOB affinity); a CASE produces what its results produce; and
 * every other expression produces numbers. Parentheses, COLLATE and a unary
 * plus are looked through. The set is not determined when an affinity it
 * depends on is not. Terminates: each step enters a strict sub-expression.
 * Source: https://sqlite.org/datatype3.html#affinity_of_expressions
 * (and `sqlite3ExprDataType()` in expr.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ValueKinds
{
    /**
     * The bit of a value that is an integer or a real number.
     */
    public const NUMBER = 0x01;

    /**
     * The bit of a value that is text.
     */
    public const TEXT = 0x02;

    /**
     * The bit of a value that is a BLOB.
     */
    public const BLOB = 0x04;

    /**
     * Answers the kinds of value an expression can produce, or null when they are not determined.
     */
    public function of(Scalar $expression, Facts $facts): ?int
    {
        while ($expression instanceof Collate || $expression instanceof Grouped || ($expression instanceof Unary && $expression->operator === UnaryOperator::Plus)) {
            $expression = $expression->operand;
        }
        if ($expression instanceof NullLiteral) {
            return 0;
        }
        if ($expression instanceof TextLiteral || ($expression instanceof Binary && $expression->operator === BinaryOperator::Concat)) {
            return self::TEXT;
        }
        if ($expression instanceof BlobLiteral) {
            return self::BLOB;
        }
        if ($expression instanceof BindParameter || $expression instanceof FunctionCall || $expression instanceof CurrentTime) {
            return self::NUMBER | self::TEXT | self::BLOB;
        }
        if ($expression instanceof CaseExpression) {
            return $this->results($expression, $facts);
        }
        if ($expression instanceof DoubleQuotedWord && ($facts->covers($expression) ? $facts->scalar($expression)->resolution : null) === null) {
            return self::TEXT;
        }
        if ($expression instanceof ColumnUse || $expression instanceof DoubleQuotedWord || $expression instanceof LiteralColumn
            || $expression instanceof ScalarSubquery || $expression instanceof Cast || $expression instanceof RowExpression) {
            return $this->affined((new ExpressionAffinity())->of($expression, $facts));
        }

        return self::NUMBER;
    }

    /**
     * Answers the kinds of value a CASE can produce: the union over its results.
     */
    public function results(CaseExpression $expression, Facts $facts): ?int
    {
        $kinds = 0;
        $results = array_map(static fn (object $branch): Scalar => $branch->then, $expression->branches);
        if ($expression->otherwise !== null) {
            $results[] = $expression->otherwise;
        }
        foreach ($results as $result) {
            $kind = $this->of($result, $facts);
            if ($kind === null) {
                return null;
            }
            $kinds |= $kind;
        }

        return $kinds;
    }

    /**
     * Answers the kinds of value an operand with an affinity can produce.
     */
    public function affined(DerivedAffinity $affinity): ?int
    {
        if (!$affinity->determined) {
            return null;
        }
        if ($affinity->numeric()) {
            return self::NUMBER | self::BLOB;
        }

        return $affinity->affinity === Affinity::Text ? self::TEXT | self::BLOB : self::NUMBER | self::TEXT | self::BLOB;
    }

    /**
     * Answers the kinds of value the result of an arm at a position can produce; an expanded star is a column reference.
     */
    public function arm(Select|ValueRow $arm, int $position, Facts $facts): ?int
    {
        if ($arm instanceof ValueRow) {
            return isset($arm->values[$position]) ? $this->of($arm->values[$position], $facts) : null;
        }
        $item = $facts->covers($arm) ? ($facts->query($arm)->projection[$position] ?? null) : null;
        if (!$item instanceof Field) {
            return null;
        }
        if ($item->expression !== null) {
            return $this->of($item->expression, $facts);
        }

        return $item->resolution instanceof ResolvedColumn ? $this->affined((new ExpressionAffinity())->resolved($item->resolution, $facts)) : null;
    }
}
