<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Ordering;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Validation\Collections;

/**
 * Builds typed statement components, preserving expression and identifier boundaries.
 *
 * @visibility SqlSemantics
 */
final class Parts
{
    /**
     * @param list<OutputColumn> $outputs
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function outputs(array $outputs, Dialect $dialect): Tree
    {
        Collections::objects($outputs, OutputColumn::class);
        foreach ($outputs as $ordinal => $output) {
            if ($output->ordinal !== $ordinal || $output->expression->type->dialect !== $dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Projection positions and dialect must match the destination statement.');
            }
        }
        $items = [];
        for ($index = 0; $index < count($outputs); $index++) {
            $output = $outputs[$index];
            $star = \SqlSemantics\Serialization\Query\PositionalStars::length($outputs, $index);
            if ($star > 0 && $output->expression instanceof \SqlSemantics\Model\Scalar\Reference\ColumnReference) {
                $items[] = new Tree('output', [\SqlSemantics\Serialization\Query\PositionalStars::star($output->expression, $dialect)]);
                $index += $star - 1;
                continue;
            }
            $items[] = new Tree('output', [$output->name !== null && $output->expression instanceof \SqlSemantics\Model\Scalar\Reference\Wildcard ? Build::parentheses($output->expression->structure()) : $output->expression->structure(), ...($output->name === null ? [] : [Build::keyword('AS'), Build::identifier([$output->name], $dialect)])]);
        }
        return Build::separated($items);
    }

    /**
     * @param list<Expression> $expressions
     */
    public static function expressions(string $keyword, array $expressions): Tree
    {
        Collections::objects($expressions, Expression::class);
        return new Tree('clause', $expressions === [] ? [] : [Build::keyword($keyword), Build::separated(array_map(static fn (Expression $value): Tree => $value->structure(), $expressions))]);
    }

    /**
     * @param list<Ordering> $orderings
     */
    public static function ordering(array $orderings): Tree
    {
        Collections::objects($orderings, Ordering::class);
        $items = array_map(static fn (Ordering $order): Tree => new Tree('ordering', [\SqlSemantics\Serialization\Query\OrderingKeys::write($order->key), Build::keyword($order->descending ? 'DESC' : 'ASC'), ...($order->nullsFirst === null ? [] : [Build::keyword($order->nullsFirst ? 'NULLS FIRST' : 'NULLS LAST')])]), $orderings);
        return new Tree('orderBy', $items === [] ? [] : [Build::keyword('ORDER BY'), Build::separated($items)]);
    }

    /**
     * @param list<list<Expression|\SqlSemantics\Model\Write\DefaultSource>> $rows
     */
    public static function rows(array $rows): Tree
    {
        foreach ($rows as $row) {
            Collections::alternatives($row, [Expression::class, \SqlSemantics\Model\Write\DefaultSource::class]);
        }
        return new Tree('rows', [Build::keyword('VALUES'), Build::separated(array_map(static fn (array $row): Tree => Build::parentheses(Build::separated(array_map(\SqlSemantics\Serialization\Write\Inputs::write(...), $row))), $rows))]);
    }

    /**
     * Embeds a whole query without its top-level terminator or ambiguous set precedence.
     */
    public static function query(\SqlSemantics\Model\BoundQuery $query, Dialect $dialect): Tree
    {
        $group = Build::parentheses(\SqlSemantics\Serialization\Statements::write($query));
        return $dialect === Dialect::Sqlite ? new Tree('query', [Build::keyword('SELECT * FROM'), $group]) : $group;
    }
}
