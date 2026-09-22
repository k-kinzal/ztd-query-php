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
        $table = $relation->declaration;
        $name = $table->schema === '' ? [$table->name] : [$table->schema, $table->name];
        $body = match (true) {
            $relation instanceof \SqlSemantics\Model\Relation\NamedTableReference => self::target($relation, $dialect),
            $relation instanceof \SqlSemantics\Model\Relation\CteReference => Build::identifier([$relation->name], $dialect),
            $relation instanceof \SqlSemantics\Model\Relation\DerivedRelation => new Tree('derived', [...($relation->lateral ? [Build::keyword('LATERAL')] : []), Build::parentheses(Queries::write($relation->query))]),
            $relation instanceof \SqlSemantics\Model\Relation\DocumentRelation => ($relation->table instanceof \SqlSemantics\Model\TableFunction\Json\JsonTable ? \SqlSemantics\Serialization\Document\JsonTables::write($relation->table, $dialect) : \SqlSemantics\Serialization\Document\XmlTables::write($relation->table, $dialect)),
            $relation instanceof \SqlSemantics\Model\Relation\FunctionRelation => Expressions::write($relation->function),
            $relation instanceof \SqlSemantics\Model\Relation\AliasedRelation => Build::parentheses(self::write($relation->input, $dialect)),
            default => throw new \SqlSemantics\Model\Validation\InvalidStructure('This relation is a scoped pseudo-row, not a FROM source.'),
        };
        $columns = $relation instanceof \SqlSemantics\Model\Relation\DocumentRelation || $relation instanceof \SqlSemantics\Model\Relation\DerivedRelation || $relation instanceof \SqlSemantics\Model\Relation\FunctionRelation || $relation instanceof \SqlSemantics\Model\Relation\AliasedRelation ? $relation->columnAliases : [];
        return new Tree('relation', [$body, ...($relation->alias === null ? [] : [Build::keyword('AS'), Build::identifier([$relation->alias], $dialect)]), ...($columns === [] ? [] : [Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], $dialect), $columns)))])]);
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
