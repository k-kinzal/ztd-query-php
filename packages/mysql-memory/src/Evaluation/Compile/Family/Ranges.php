<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\FieldConstants;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\Membership;
use MySqlMemory\Evaluation\Operator\Comparison\Range;
use MySqlMemory\Evaluation\Operator\DoubleOperand;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Compiles the predicates that compare a value with several others: [NOT] BETWEEN, and [NOT] IN with a list.
 *
 * The type each pair compares in decides whether the values are read as doubles, and MySQL 5.6
 * and 5.7 convert a constant value compared as strings of another character set once for the
 * statement (verified on live 8.4, 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html,
 * https://dev.mysql.com/doc/refman/5.7/en/type-conversion.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Ranges
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles [NOT] BETWEEN.
     *
     * Values compared as doubles are read as doubles once for each row. When one bound compares as
     * a string and the other as a number, all three compare as doubles, and so they do when a
     * temporal value other than a column, whose type a number is read in, is compared with a
     * number and a string. A JSON value is compared
     * as its text, or as a double read from it, with a warning that the comparison of JSON values is
     * not supported there (verified on a live 8.4 server). MySQL 5.6 and 5.7 convert a constant
     * value compared with the bounds as strings of another character set once for the statement
     * (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function between(Between $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $fields = new FieldConstants($this->compiler);
        $low = $fields->stored($node->operand, $operand, $node->low, $this->compiler->compile($node->low, $scope));
        $high = $fields->stored($node->operand, $operand, $node->high, $this->compiler->compile($node->high, $scope));
        $low = (new \MySqlMemory\Evaluation\Compile\FieldStrings($this->compiler))->check($node->operand, $operand, $node->low, $low, false);
        $high = (new \MySqlMemory\Evaluation\Compile\FieldStrings($this->compiler))->check($node->operand, $operand, $node->high, $high, false);
        $connection = $this->compiler->settings->connectionCollation;
        $json = array_filter([$operand, $low, $high], static fn (Evaluable $value): bool => $value->domain()->kind === Kind::Json) !== [];
        if ($json) {
            $this->compiler->connection->context->diagnostics->warning(StatementError::NotSupportedYet, StatementError::NotSupportedYet->message('comparison of JSON in the BETWEEN operator'));
        }
        $text = static fn (Evaluable $value): Domain => $value->domain()->kind === Kind::Json ? Domain::string(4294967295, Collation::known('utf8mb4_bin')) : $value->domain();
        $modes = [Comparator::of($text($operand), $text($low), 'between', $connection)->mode, Comparator::of($text($operand), $text($high), 'between', $connection)->mode];
        $numeric = static fn (Kind $mode): bool => in_array($mode, [Kind::Double, Kind::Decimal, Kind::Integer], true);
        $stringBound = $low->domain()->kind === Kind::String || $high->domain()->kind === Kind::String;
        $field = $node->operand;
        while ($field instanceof Grouped) {
            $field = $field->operand;
        }
        $temporalPair = !$field instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse && (($modes[0]->temporal() && $numeric($modes[1])) || ($modes[1]->temporal() && $numeric($modes[0])));
        if ($modes === [Kind::Double, Kind::Double] || (in_array(Kind::String, $modes, true) && ($numeric($modes[0]) || $numeric($modes[1]))) || ($temporalPair && $stringBound)) {
            $operand = $operand->domain()->kind === Kind::Double ? $operand : new DoubleOperand($operand, false);
            $low = $low->domain()->kind === Kind::Double ? $low : new DoubleOperand($low, false);
            $high = $high->domain()->kind === Kind::Double ? $high : new DoubleOperand($high, false);
        }
        if ($modes === [Kind::String, Kind::String] && $this->compiler->settings->legacy()) {
            [$collation] = \MySqlMemory\Typing\Collations::aggregate([$operand->domain(), $low->domain(), $high->domain()], 'between', $connection, true);
            $operand = \MySqlMemory\Evaluation\Operator\Transcoded::of($operand, $collation, $this->compiler->constancy($node->operand) === Constancy::Resolved, $this->compiler->connection->context);
        }
        $comparator = static function (Evaluable $left, Evaluable $right) use ($text, $connection, $json): Comparator {
            $comparator = Comparator::of($json ? $text($left) : $left->domain(), $json ? $text($right) : $right->domain(), 'between', $connection);

            return $json ? new Comparator($comparator->mode, $left->domain(), $right->domain(), $comparator->collation) : $comparator;
        };

        return new Range($operand, $low, $high, $comparator($operand, $low), $comparator($operand, $high), $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles [NOT] IN with a list.
     *
     * A list of one value is the comparison `=`, or `<>` for NOT IN, as the server rewrites it. A
     * value compared with every element as a double is read as a double once for each row. MySQL
     * 5.6 and 5.7 convert a constant value compared with constant elements as strings of another
     * character set once for the statement, and compare it in that form with the other elements
     * too (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function inList(InList $node, Scope $scope): Evaluable
    {
        if ($this->compiler->rows->elements($node->operand) !== null) {
            return $this->compiler->rows->in($node->operand, $node->elements, $node->negated, $scope);
        }
        if (count($node->elements) === 1 && $this->compiler->rows->elements($node->elements[0]) === null) {
            return $this->compiler->operators->compare($node->negated ? ComparisonOperator::NotEqual : ComparisonOperator::Equal, $node->operand, $node->elements[0], $scope, $node);
        }
        $connection = $this->compiler->settings->connectionCollation;
        $operand = $this->compiler->compile($node->operand, $scope);
        $compiled = [];
        $modes = [];
        $fixed = true;
        $fields = new FieldConstants($this->compiler);
        foreach ($node->elements as $element) {
            $compiled[] = (new \MySqlMemory\Evaluation\Compile\FieldStrings($this->compiler))->check($node->operand, $operand, $element, $fields->stored($node->operand, $operand, $element, $this->compiler->compile($element, $scope)), false);
            $modes[] = Comparator::of($operand->domain(), $compiled[count($compiled) - 1]->domain(), 'in', $connection)->mode;
            $fixed = $fixed && $this->compiler->constancy($element)->constant();
        }
        $strings = array_values(array_filter($compiled, static fn (int $index): bool => $modes[$index] === Kind::String, ARRAY_FILTER_USE_KEY));
        if ($fixed && $strings !== [] && $this->compiler->settings->legacy()) {
            [$collation] = \MySqlMemory\Typing\Collations::aggregate([$operand->domain(), ...array_map(static fn (Evaluable $element): Domain => $element->domain(), $strings)], 'in', $connection, true);
            $operand = \MySqlMemory\Evaluation\Operator\Transcoded::of($operand, $collation, $this->compiler->constancy($node->operand) === Constancy::Resolved, $this->compiler->connection->context);
        }
        $fixed = $fixed && count(array_unique(array_map(static fn (Kind $mode): string => $mode->name, $modes))) === 1;
        if (array_unique(array_map(static fn (Kind $mode): string => $mode->name, $modes)) === [Kind::Double->name]) {
            $operand = $operand->domain()->kind === Kind::Double ? $operand : new DoubleOperand($operand, false);
            $compiled = array_map(static fn (Evaluable $element): Evaluable => $element->domain()->kind === Kind::Double ? $element : new DoubleOperand($element, false), $compiled);
        }
        $elements = array_map(static fn (Evaluable $element): array => [$element, Comparator::of($operand->domain(), $element->domain(), 'in', $connection)], $compiled);
        $types = $this->compiler->settings->legacy() ? $this->comparisons($operand->domain(), array_map(static fn (Evaluable $element): Domain => $element->domain(), $compiled)) : [];

        return new Membership($operand, $elements, $node->negated, $this->compiler->domain($node), $fixed, $types, $this->compiler->settings->release() === GrammarRelease::MySql5744);
    }

    /**
     * Answers the type MySQL 5.6 and 5.7 compare a value with each element of IN as, null for a NULL element; or an empty list when they compare with every element as one type.
     *
     * Two strings compare as strings, two integers as integers, an integer or a decimal with an
     * integer or a decimal as decimals, and anything else as doubles; a temporal value is a string
     * and a YEAR or BIT value an integer to them.
     * Source: https://dev.mysql.com/doc/refman/5.7/en/type-conversion.html.
     *
     * @param list<Domain> $elements
     * @return list<string|null>
     */
    public function comparisons(Domain $operand, array $elements): array
    {
        $result = static fn (Kind $kind): string => match ($kind) {
            Kind::Integer, Kind::Year, Kind::Bit => 'integer',
            Kind::Decimal => 'decimal',
            Kind::Double => 'double',
            Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Json, Kind::Null => 'string',
        };
        $left = $result($operand->kind);
        $types = array_map(static fn (Domain $element): ?string => match (true) {
            $element->kind === Kind::Null => null,
            $left === 'string' && $result($element->kind) === 'string' => 'string',
            $left === 'integer' && $result($element->kind) === 'integer' => 'integer',
            in_array($left, ['integer', 'decimal'], true) && in_array($result($element->kind), ['integer', 'decimal'], true) => 'decimal',
            default => 'double',
        }, $elements);

        return count(array_unique(array_filter($types, static fn (?string $type): bool => $type !== null))) > 1 ? $types : [];
    }
}
