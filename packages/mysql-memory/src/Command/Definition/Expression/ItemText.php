<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;

/**
 * Writes an expression of a table definition as the server stores it: a generated column, an expression default or a CHECK constraint, as SHOW CREATE TABLE shows it.
 *
 * Keywords and function names are in lower case; a column is written in backticks, by its
 * declared name or as written; a string literal carries its character set introducer, that of
 * the connection when none is written; an operation is enclosed in parentheses; a negative
 * number is -(n); NOT is the opposite comparison, or a comparison of 0 with its operand; AND,
 * OR and XOR compare each operand that is not a condition with 0; a hexadecimal or bit literal
 * is 0x followed by its bytes. An expression the writer does not know is written as SQL
 * Semantics renders it (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html.
 *
 * @visibility MySqlMemory
 */
final class ItemText
{
    /**
     * The writer of function calls, casts and temporal arithmetic.
     */
    public readonly CallText $calls;

    /**
     * @param array<string, string> $names The declared name of each column, by lowercase name; a column not in it is written as written
     * @param string $charset The character set a string literal without an introducer is in
     * @param GrammarRelease $release The release whose rendering is the fallback
     */
    public function __construct(public readonly array $names, public readonly string $charset, public readonly GrammarRelease $release = GrammarRelease::MySql847)
    {
        $this->calls = new CallText($this);
    }

    /**
     * Writes an expression.
     */
    public function text(Scalar $scalar): string
    {
        return $this->scalar($scalar) ?? $this->rendered($scalar);
    }

    /**
     * Writes an expression as SQL Semantics renders it.
     */
    public function rendered(Scalar $scalar): string
    {
        $out = new Output(new Codec($this->release));
        $out->layout(null, $scalar);

        return (new Lexical())->join($out->pieces());
    }

    /**
     * Writes an expression, or answers null for one the writer does not know.
     */
    public function scalar(Scalar $scalar): ?string
    {
        return match (true) {
            $scalar instanceof Grouped => $this->scalar($scalar->operand),
            $scalar instanceof ColumnUse => '`' . str_replace('`', '``', $this->names[mb_strtolower($scalar->name->value)] ?? $scalar->name->value) . '`',
            $scalar instanceof NumberLiteral, $scalar instanceof SignedLiteral, $scalar instanceof StringLiteral, $scalar instanceof NullLiteral, $scalar instanceof BooleanLiteral, $scalar instanceof RadixLiteral, $scalar instanceof TemporalLiteral => $this->literal($scalar),
            $scalar instanceof Comparison => $this->binary($scalar->left, $scalar->operator === ComparisonOperator::NotEqual ? '<>' : $scalar->operator->value, $scalar->right),
            $scalar instanceof Arithmetic => $this->binary($scalar->left, $scalar->operator === ArithmeticOperator::Modulo ? '%' : $scalar->operator->value, $scalar->right),
            $scalar instanceof Logical => $this->logical($scalar->operator, $scalar->left, $scalar->right),
            $scalar instanceof Concatenation => $this->calls->call('concat', [$scalar->left, $scalar->right]),
            $scalar instanceof Not => $this->negated($scalar->operand),
            $scalar instanceof Unary => $this->unary($scalar),
            $scalar instanceof CaseExpression => $this->branches($scalar),
            default => $this->predicate($scalar) ?? $this->calls->scalar($scalar),
        };
    }

    /**
     * Writes a predicate: a null test, a truth test, BETWEEN, IN, LIKE, REGEXP, SOUNDS LIKE or COLLATE; null for another expression.
     */
    public function predicate(Scalar $scalar): ?string
    {
        return match (true) {
            $scalar instanceof NullTest => $this->wrap($scalar->operand, '(', $scalar->negated ? ' is not null)' : ' is null)'),
            $scalar instanceof TruthTest => $this->truth($scalar),
            $scalar instanceof Between => $this->between($scalar),
            $scalar instanceof InList => $this->in($scalar),
            $scalar instanceof Like => $this->like($scalar),
            $scalar instanceof Regexp => $scalar->negated ? $this->wrap(new Regexp($scalar->operand, $scalar->pattern), '(not(', '))') : $this->calls->call('regexp_like', [$scalar->operand, $scalar->pattern]),
            $scalar instanceof SoundsLike => $this->joined([$this->calls->call('soundex', [$scalar->operand]), $this->calls->call('soundex', [$scalar->pattern])], '(', ' = ', ')'),
            $scalar instanceof Collated => $this->wrap($scalar->operand, '(', ' collate ' . strtolower($scalar->collation->value) . ')'),
            default => null,
        };
    }

    /**
     * Writes a literal: a negative number as -(n), a string with its introducer, a hexadecimal or bit literal as 0x and its bytes, and a temporal literal with its keyword.
     */
    public function literal(Scalar $literal): ?string
    {
        return match (true) {
            $literal instanceof NumberLiteral => $literal->text,
            $literal instanceof SignedLiteral => $literal->negative ? '-(' . $literal->number->text . ')' : $literal->number->text,
            $literal instanceof StringLiteral => ($literal->national ? '_utf8mb3' : ($literal->introducer === null && $this->legacy() ? '' : '_' . strtolower($literal->introducer->value ?? $this->charset))) . $this->quoted($literal->value()),
            $literal instanceof NullLiteral => 'NULL',
            $literal instanceof BooleanLiteral => $literal->value ? 'true' : 'false',
            $literal instanceof RadixLiteral => '0x' . ($literal->radix === Radix::Hexadecimal ? $literal->digits : bin2hex($this->bytes($literal->digits))),
            $literal instanceof TemporalLiteral => $literal->form->value . $this->quoted($literal->text),
            default => null,
        };
    }

    /**
     * Tells whether the release is MySQL 5.6 or 5.7, which writes a string without an introducer as written.
     */
    public function legacy(): bool
    {
        return $this->release === GrammarRelease::MySql5651 || $this->release === GrammarRelease::MySql5744;
    }

    /**
     * Answers the bytes of a bit literal, the first byte holding the leading bits.
     */
    public function bytes(string $bits): string
    {
        $bits = $bits === '' ? '0' : $bits;
        $padded = str_pad($bits, (int) ceil(strlen($bits) / 8) * 8, '0', STR_PAD_LEFT);

        return implode('', array_map(static fn (string $byte): string => chr((int) bindec($byte)), str_split($padded, 8)));
    }

    /**
     * Quotes a string as the server writes a literal: a backslash, a quote and the control characters escaped.
     */
    public function quoted(string $text): string
    {
        return "'" . strtr($text, ['\\' => '\\\\', "'" => "\\'", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1A" => '\\Z']) . "'";
    }

    /**
     * Writes a binary operation in parentheses.
     */
    public function binary(Scalar $left, string $operator, Scalar $right): ?string
    {
        return $this->joined([$this->scalar($left), $this->scalar($right)], '(', ' ' . $operator . ' ', ')');
    }

    /**
     * Joins written parts, or answers null when one could not be written.
     *
     * @param list<string|null> $parts
     */
    public function joined(array $parts, string $before, string $separator, string $after): ?string
    {
        if (in_array(null, $parts, true)) {
            return null;
        }

        return $before . implode($separator, $parts) . $after;
    }

    /**
     * Writes an expression between a prefix and a suffix.
     */
    public function wrap(Scalar $operand, string $before, string $after): ?string
    {
        $text = $this->scalar($operand);

        return $text === null ? null : $before . $text . $after;
    }

    /**
     * Writes AND, OR or XOR, each operand that is not a condition compared with 0.
     */
    public function logical(LogicalOperator $operator, Scalar $left, Scalar $right): ?string
    {
        return $this->joined([$this->condition($left), $this->condition($right)], '(', ' ' . strtolower($operator->value) . ' ', ')');
    }

    /**
     * Writes an operand of a logical operator: a condition as it is, another expression compared with 0.
     */
    public function condition(Scalar $operand): ?string
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }

        return (new Conditions())->boolean($operand) ? $this->scalar($operand) : $this->wrap($operand, '(0 <> ', ')');
    }

    /**
     * Writes a negation: the opposite comparison, AND and OR negated by De Morgan's laws, the opposite null test, or a comparison of the operand with 0.
     */
    public function negated(Scalar $operand): ?string
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        $opposite = $operand instanceof Comparison ? $this->opposite($operand->operator) : null;

        return match (true) {
            $operand instanceof Comparison && $opposite !== null => $this->binary($operand->left, $opposite, $operand->right),
            $operand instanceof Logical && $operand->operator !== LogicalOperator::Xor => $this->joined([$this->negated($operand->left), $this->negated($operand->right)], '(', $operand->operator === LogicalOperator::And ? ' or ' : ' and ', ')'),
            $operand instanceof NullTest => $this->wrap($operand->operand, '(', $operand->negated ? ' is null)' : ' is not null)'),
            $operand instanceof Between => $this->between(new Between($operand->operand, $operand->low, $operand->high, !$operand->negated)),
            $operand instanceof InList => $this->in(new InList($operand->operand, $operand->elements, !$operand->negated)),
            (new Conditions())->boolean($operand) => $this->wrap($operand, '(not(', '))'),
            default => $this->wrap($operand, '(0 = ', ')'),
        };
    }

    /**
     * Answers the comparison operator that negates another, or null for <=>, which has none.
     */
    public function opposite(ComparisonOperator $operator): ?string
    {
        return match ($operator) {
            ComparisonOperator::Equal => '<>',
            ComparisonOperator::NotEqual => '=',
            ComparisonOperator::Less => '>=',
            ComparisonOperator::LessOrEqual => '>',
            ComparisonOperator::Greater => '<=',
            ComparisonOperator::GreaterOrEqual => '<',
            ComparisonOperator::NullSafeEqual => null,
        };
    }

    /**
     * Writes a unary operator: + as its operand alone, ! as NOT, and - or ~ before its operand in parentheses.
     */
    public function unary(Unary $unary): ?string
    {
        return match ($unary->operator) {
            UnaryOperator::Plus => $this->scalar($unary->operand),
            UnaryOperator::Not => $this->negated($unary->operand),
            UnaryOperator::Minus, UnaryOperator::Invert => $this->wrap($unary->operand, $unary->operator->value . '(', ')'),
        };
    }

    /**
     * Writes IS [NOT] TRUE, FALSE or UNKNOWN: UNKNOWN as a null test, TRUE and FALSE over a condition.
     */
    public function truth(TruthTest $test): ?string
    {
        if ($test->truth === Truth::Unknown) {
            return $this->wrap($test->operand, '(', $test->negated ? ' is not null)' : ' is null)');
        }
        $operand = $this->condition($test->operand);

        return $operand === null ? null : '(' . $operand . ' is ' . ($test->negated ? 'not ' : '') . strtolower($test->truth->value) . ')';
    }

    /**
     * Writes BETWEEN.
     */
    public function between(Between $between): ?string
    {
        $range = $this->joined([$this->scalar($between->low), $this->scalar($between->high)], '', ' and ', ')');

        return $this->joined([$this->scalar($between->operand), $range], '(', $between->negated ? ' not between ' : ' between ', '');
    }

    /**
     * Writes IN over a list; over one element, it is a comparison with it.
     */
    public function in(InList $in): ?string
    {
        if (count($in->elements) === 1) {
            return $this->binary($in->operand, $in->negated ? '<>' : '=', $in->elements[0]);
        }
        $elements = $this->joined(array_map($this->scalar(...), $in->elements), '(', ',', ')');

        return $this->joined([$this->scalar($in->operand), $elements], '(', $in->negated ? ' not in ' : ' in ', ')');
    }

    /**
     * Writes LIKE, with its ESCAPE; NOT LIKE as the negation of LIKE.
     */
    public function like(Like $like): ?string
    {
        if ($like->negated) {
            return $this->wrap(new Like($like->operand, $like->pattern, $like->escape), '(not(', '))');
        }
        $text = $this->binary($like->operand, 'like', $like->pattern);
        if ($text === null || $like->escape === null) {
            return $text;
        }
        $escape = $this->scalar($like->escape);

        return $escape === null ? null : substr($text, 0, -1) . ' escape ' . $escape . ')';
    }

    /**
     * Writes a CASE expression.
     */
    public function branches(CaseExpression $case): ?string
    {
        $parts = [];
        if ($case->operand !== null) {
            $parts[] = $this->scalar($case->operand);
        }
        foreach ($case->branches as $branch) {
            $parts[] = $this->joined([$this->scalar($branch->condition), $this->scalar($branch->result)], 'when ', ' then ', '');
        }
        if ($case->else !== null) {
            $parts[] = $this->wrap($case->else, 'else ', '');
        }

        return $this->joined($parts, '(case ', ' ', ' end)');
    }
}
