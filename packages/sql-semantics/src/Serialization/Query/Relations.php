<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Join;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Serialization\Expressions;

/**

 * Writes relation identities and join operands. @visibility SqlSemantics

 */
final class Relations
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(TableUse|Join $relation, Dialect $dialect): Tree
    {
        if ($relation instanceof Join) {
            return Joins::write($relation, $dialect);
        }
        $body = match (true) {
            $relation instanceof \SqlSemantics\Model\Relation\NamedTableReference => new Tree('partitioned_table', [self::target($relation, $dialect), ...self::partitions($relation, $dialect)]),
            $relation instanceof \SqlSemantics\Model\Relation\CteReference => Build::identifier([$relation->name], $dialect),
            $relation instanceof \SqlSemantics\Model\Relation\DerivedRelation => new Tree('derived', [...($relation->lateral ? [Build::keyword('LATERAL')] : []), Build::parentheses(Queries::write($relation->query))]),
            $relation instanceof \SqlSemantics\Model\Relation\DocumentRelation => ($relation->table instanceof \SqlSemantics\Model\TableFunction\Json\JsonTable ? \SqlSemantics\Serialization\Document\JsonTables::write($relation->table, $dialect) : \SqlSemantics\Serialization\Document\XmlTables::write($relation->table, $dialect)),
            $relation instanceof \SqlSemantics\Model\Relation\FunctionRelation => Expressions::write($relation->function),
            $relation instanceof \SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation => RowsFromTables::write($relation->table),
            $relation instanceof \SqlSemantics\Model\Relation\AliasedRelation => Build::parentheses(self::write($relation->input, $dialect)),
            default => throw new \SqlSemantics\Model\Validation\InvalidStructure('This relation is a scoped pseudo-row, not a FROM source.'),
        };
        $columns = $relation instanceof \SqlSemantics\Model\Relation\DocumentRelation || $relation instanceof \SqlSemantics\Model\Relation\DerivedRelation || $relation instanceof \SqlSemantics\Model\Relation\FunctionRelation || $relation instanceof \SqlSemantics\Model\Relation\AliasedRelation || $relation instanceof \SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation ? $relation->columnAliases : [];
        return new Tree('relation', [$body, ...($relation->alias === null ? [] : [Build::keyword('AS'), Build::identifier([$relation->alias], $dialect)]), ...($columns === [] ? [] : [Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], $dialect), $columns)))]), ...self::indexHints($relation, $dialect), ...self::indexing($relation, $dialect), ...self::sample($relation)]);
    }

    /**
     * Writes the MySQL index hints of a table occurrence after its alias, each with its FOR clause and index list.
     * @return list<Tree>
     */
    public static function indexHints(TableUse $relation, Dialect $dialect): array
    {
        if (!$relation instanceof \SqlSemantics\Model\Relation\TableReference) {
            return [];
        }
        return array_map(static fn (\SqlSemantics\Model\Query\Optimization\IndexHint $hint): Tree => new Tree('index-hint', [Build::keyword($hint->action->value . ' INDEX' . ($hint->scope === null ? '' : ' FOR ' . $hint->scope->value)), Build::parentheses(Build::separated(array_map(static fn (string $index): Tree => Build::identifier([$index], $dialect), $hint->indexes)))]), $relation->indexHints);
    }

    /**
     * Writes the MySQL partition selection of a table occurrence after its name.
     * @return list<Tree>
     */
    public static function partitions(TableUse $relation, Dialect $dialect): array
    {
        if (!$relation instanceof \SqlSemantics\Model\Relation\TableReference || $relation->partitions === null) {
            return [];
        }
        return [Build::keyword('PARTITION'), Build::parentheses(Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], $dialect), $relation->partitions->names)))];
    }

    /**
     * Writes the SQLite INDEXED BY or NOT INDEXED directive of a table occurrence after its alias.
     * @return list<Tree>
     */
    public static function indexing(TableUse $relation, Dialect $dialect): array
    {
        $indexing = $relation instanceof \SqlSemantics\Model\Relation\TableReference ? $relation->indexing : null;
        return match (true) {
            $indexing instanceof \SqlSemantics\Model\Query\Optimization\IndexedBy => [Build::keyword('INDEXED BY'), Build::identifier([$indexing->index], $dialect)],
            $indexing instanceof \SqlSemantics\Model\Query\Optimization\NotIndexed => [Build::keyword('NOT INDEXED')],
            default => [],
        };
    }

    /**
     * Writes the TABLESAMPLE clause of a table occurrence last, with its REPEATABLE seed.
     * @return list<Tree>
     */
    public static function sample(TableUse $relation): array
    {
        $sample = $relation instanceof \SqlSemantics\Model\Relation\NamedTableReference ? $relation->sample : null;
        if ($sample === null) {
            return [];
        }
        $method = $sample->method instanceof \SqlSemantics\Model\Query\Sampling\SamplingMethod ? Build::keyword($sample->method->value) : Build::identifier($sample->method->parts, Dialect::PostgreSql);
        $arguments = Build::parentheses(Build::separated(array_map(Expressions::write(...), $sample->arguments)));
        return [Build::keyword('TABLESAMPLE'), $method, $arguments, ...($sample->repeatable === null ? [] : [Build::keyword('REPEATABLE'), Build::parentheses(Expressions::write($sample->repeatable))])];
    }

    /**
     * Writes a single-table deletion target; MySQL writes its alias before the partition selection there.
     */
    public static function deletion(TableUse $table, Dialect $dialect): Tree
    {
        if ($dialect !== Dialect::MySql || $table->alias === null || self::partitions($table, $dialect) === []) {
            return self::write($table, $dialect);
        }
        return new Tree('relation', [self::target($table, $dialect), Build::keyword('AS'), Build::identifier([$table->alias], $dialect), ...self::partitions($table, $dialect)]);
    }

    /**
     * Serializes a required relation name and its optional alias in a write-target position.
     */
    public static function target(TableUse $table, Dialect $dialect): Tree
    {
        if ($table instanceof \SqlSemantics\Model\Relation\NamedTableReference) {
            return new Tree('named_table', [...($table instanceof \SqlSemantics\Model\Relation\OnlyTableReference ? [Build::keyword('ONLY')] : []), Build::identifier($table->name->parts, $dialect)]);
        }
        $declaration = $table->declaration;
        return Build::identifier($declaration->schema === '' ? [$declaration->name] : [$declaration->schema, $declaration->name], $dialect);
    }
}
