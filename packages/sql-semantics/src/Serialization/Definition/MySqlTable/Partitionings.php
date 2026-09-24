<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlTable;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Partition;
use SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes MySQL PARTITION BY clauses and partition definitions from their typed operands.
 * @visibility SqlSemantics
 */
final class Partitionings
{
    /**
     * Writes the complete clause.
     */
    public static function write(Partition\TablePartitioning $partitioning): Tree
    {
        return new Tree('partitioning', [
            Build::keyword('PARTITION BY'),
            self::function($partitioning->function),
            ...($partitioning->partitionCount === null ? [] : [Build::keyword('PARTITIONS ' . $partitioning->partitionCount)]),
            ...($partitioning->subpartitioning === null ? [] : [Build::keyword('SUBPARTITION BY'), self::function($partitioning->subpartitioning)]),
            ...($partitioning->subpartitionCount === null ? [] : [Build::keyword('SUBPARTITIONS ' . $partitioning->subpartitionCount)]),
            ...($partitioning->partitions === [] ? [] : [self::definitions($partitioning->partitions)]),
        ]);
    }

    /**
     * Writes a partitioning function.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function function(Partition\PartitionFunction $function): Tree
    {
        return match (true) {
            $function instanceof Partition\HashPartitioning => new Tree('hash', [Build::keyword(($function->linear ? 'LINEAR ' : '') . 'HASH'), Build::parentheses(Expressions::write($function->expression))]),
            $function instanceof Partition\KeyPartitioning => new Tree('key', [Build::keyword(($function->linear ? 'LINEAR ' : '') . 'KEY' . ($function->algorithm === null ? '' : ' ALGORITHM = ' . $function->algorithm)), self::columns($function->columns)]),
            $function instanceof Partition\ExpressionPartitioning => new Tree('range', [Build::keyword($function->strategy->value), Build::parentheses(Expressions::write($function->expression))]),
            $function instanceof Partition\ColumnsPartitioning => new Tree('columns', [Build::keyword($function->strategy->value . ' COLUMNS'), self::columns($function->columns)]),
            default => throw new \SqlSemantics\Model\Validation\InvalidStructure('Unclassified partitioning function.'),
        };
    }

    /**
     * Writes a parenthesized list of partition definitions.
     * @param list<Partition\PartitionDefinition> $partitions
     */
    public static function definitions(array $partitions): Tree
    {
        return Build::parentheses(Build::separated(array_map(self::definition(...), $partitions)));
    }

    /**
     * Writes one partition definition.
     */
    public static function definition(Partition\PartitionDefinition $partition): Tree
    {
        $subpartitions = array_map(static fn (Partition\SubpartitionDefinition $subpartition): Tree => new Tree('subpartition', [Build::keyword('SUBPARTITION'), Build::identifier([$subpartition->name], Dialect::MySql), self::properties($subpartition->properties)]), $partition->subpartitions);
        return new Tree('partition', [
            Build::keyword('PARTITION'),
            Build::identifier([$partition->name], Dialect::MySql),
            ...($partition->values === null ? [] : [self::values($partition->values)]),
            self::properties($partition->properties),
            ...($subpartitions === [] ? [] : [Build::parentheses(Build::separated($subpartitions))]),
        ]);
    }

    /**
     * Writes VALUES LESS THAN or VALUES IN; a single MAXVALUE bound and single-value tuples use the short forms.
     */
    public static function values(Partition\RangeBound|Partition\ListBound $values): Tree
    {
        if ($values instanceof Partition\RangeBound) {
            $bound = array_map(static fn (Expression|RangeBoundary $value): Tree => $value instanceof Expression ? Expressions::write($value) : Build::keyword($value->value), $values->bound);
            return new Tree('less-than', [Build::keyword('VALUES LESS THAN'), $values->bound === [RangeBoundary::MaxValue] ? $bound[0] : Build::parentheses(Build::separated($bound))]);
        }
        $single = array_filter($values->tuples, static fn (array $tuple): bool => count($tuple) !== 1) === [];
        $tuples = array_map(static fn (array $tuple): Tree => $single ? Expressions::write($tuple[0]) : Build::parentheses(Build::separated(array_map(Expressions::write(...), $tuple))), $values->tuples);
        return new Tree('in', [Build::keyword('VALUES IN'), Build::parentheses(Build::separated($tuples))]);
    }

    /**
     * Writes partition storage options.
     */
    public static function properties(Partition\PartitionProperties $properties): Tree
    {
        $parts = [];
        foreach (['ENGINE' => $properties->engine, 'TABLESPACE' => $properties->tablespace] as $keyword => $name) {
            if ($name !== null) {
                array_push($parts, Build::keyword($keyword . ' ='), Build::identifier([$name], Dialect::MySql));
            }
        }
        foreach (['COMMENT' => $properties->comment, 'DATA DIRECTORY' => $properties->dataDirectory, 'INDEX DIRECTORY' => $properties->indexDirectory] as $keyword => $text) {
            if ($text !== null) {
                array_push($parts, Build::keyword($keyword . ' ='), new Atom('literal', Literal::encode($text, Dialect::MySql)[0]));
            }
        }
        foreach (['MAX_ROWS' => $properties->maxRows, 'MIN_ROWS' => $properties->minRows, 'NODEGROUP' => $properties->nodeGroup] as $keyword => $number) {
            if ($number !== null) {
                $parts[] = Build::keyword($keyword . ' = ' . $number);
            }
        }
        return new Tree('partition-properties', $parts);
    }

    /**
     * Writes a parenthesized column name list.
     * @param list<string> $columns
     */
    public static function columns(array $columns): Tree
    {
        return Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], Dialect::MySql), $columns)));
    }
}
