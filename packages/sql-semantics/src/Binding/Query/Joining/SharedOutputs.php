<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Joining;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\JoinKind;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Relation\Joining\SharedColumn;

/**
 * Computes dialect-specific USING/NATURAL output order without flattening nested joins.
 * @visibility SqlSemantics
 */
final class SharedOutputs
{
    /**
     * @param list<SharedColumn> $shared Matching columns of this join only
     * @return list<OutputColumn> Complete visible joined row
     */
    public static function using(Scope $left, Scope $right, array $shared, JoinKind $kind, Node $source): array
    {
        $identifiers = $left->identifiers;
        $a = RowNamespace::read($left, $source);
        $b = RowNamespace::read($right, $source);
        if ($identifiers->dialect === Dialect::MySql && $kind === JoinKind::Right) {
            [$a, $b] = [$b, $a];
        }
        $remaining = array_values(array_filter($b, static fn (OutputColumn $column): bool => self::find($shared, $column->name, $identifiers) === null));
        if ($identifiers->dialect === Dialect::Sqlite) {
            $first = array_map(static fn (OutputColumn $column): OutputColumn => new OutputColumn($column->ordinal, $column->name, self::find($shared, $column->name, $identifiers)->output ?? $column->expression), $a);
            return RowNamespace::positions([...$first, ...$remaining]);
        }
        $common = $identifiers->dialect === Dialect::MySql ? self::ordered($a, $shared, $identifiers) : $shared;
        $first = array_map(static fn (SharedColumn $column): OutputColumn => new OutputColumn(0, $column->name, $column->output), $common);
        $leftover = array_values(array_filter($a, static fn (OutputColumn $column): bool => self::find($shared, $column->name, $identifiers) === null));
        return RowNamespace::positions([...$first, ...$leftover, ...$remaining]);
    }

    /**
     * @param list<OutputColumn> $first Columns in the first input's order
     * @param list<SharedColumn> $shared Explicit shared columns
     * @return list<SharedColumn> Shared columns in MySQL's first-input order
     */
    public static function ordered(array $first, array $shared, Identifiers $identifiers): array
    {
        $result = [];
        foreach ($first as $column) {
            $match = self::find($shared, $column->name, $identifiers);
            if ($match !== null && !in_array($match, $result, true)) {
                $result[] = $match;
            }
        }
        foreach ($shared as $column) {
            if (!in_array($column, $result, true)) {
                $result[] = $column;
            }
        }
        return $result;
    }

    /**
     * @param list<SharedColumn> $shared This join's explicit matching columns
     */
    public static function find(array $shared, ?string $name, Identifiers $identifiers): ?SharedColumn
    {
        foreach ($shared as $column) {
            if ($name !== null && $identifiers->equal($column->name, $name)) {
                return $column;
            }
        }
        return null;
    }

    /**
     * @param array<int|string, \SqlSemantics\Model\Expression> $left Left visible names
     * @param array<int|string, \SqlSemantics\Model\Expression> $right Right visible names
     * @return list<string> NATURAL names under the dialect's identifier equality
     */
    public static function common(array $left, array $right, Identifiers $identifiers): array
    {
        return array_values(array_filter(array_map(strval(...), array_keys($left)), static fn (string $name): bool => array_filter(array_keys($right), static fn (int|string $other): bool => $identifiers->equal($name, (string) $other)) !== []));
    }
}
