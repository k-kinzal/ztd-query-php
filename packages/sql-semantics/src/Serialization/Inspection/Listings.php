<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Inspection;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection\Schema;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes SHOW listings from their database, table, detail, and restriction operands.
 * @visibility SqlSemantics
 */
final class Listings
{
    /**
     * Synonymous keywords are written in one canonical spelling.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Schema\ShowDatabasesStatement => self::listing('DATABASES', null, $statement->filter),
            $statement instanceof Schema\ShowTablesStatement => self::listing(self::detail($statement->extended, $statement->full) . 'TABLES', $statement->database, $statement->filter),
            $statement instanceof Schema\ShowColumnsStatement => self::described(self::detail($statement->extended, $statement->full) . 'COLUMNS', $statement->table, $statement->filter),
            $statement instanceof Schema\ShowIndexesStatement => self::described(self::detail($statement->extended, false) . 'INDEX', $statement->table, $statement->condition),
            $statement instanceof Schema\ShowTableStatusStatement => self::listing('TABLE STATUS', $statement->database, $statement->filter),
            $statement instanceof Schema\ShowOpenTablesStatement => self::listing('OPEN TABLES', $statement->database, $statement->filter),
            $statement instanceof Schema\ShowTriggersStatement => self::listing('TRIGGERS', $statement->database, $statement->filter),
            $statement instanceof Schema\ShowEventsStatement => self::listing('EVENTS', $statement->database, $statement->filter),
            $statement instanceof Schema\ShowCharacterSetsStatement => self::listing('CHARACTER SET', null, $statement->filter),
            $statement instanceof Schema\ShowCollationsStatement => self::listing('COLLATION', null, $statement->filter),
            default => null,
        };
    }

    /**
     * Writes the EXTENDED and FULL modifiers in grammar order.
     */
    public static function detail(bool $extended, bool $full): string
    {
        return ($extended ? 'EXTENDED ' : '') . ($full ? 'FULL ' : '');
    }

    /**
     * Writes a database-scoped listing.
     */
    public static function listing(string $keyword, ?string $database, PatternFilter|ConditionFilter|null $filter): Tree
    {
        return new Tree('show', [Build::keyword('SHOW ' . $keyword), ...Filters::database($database), ...Filters::write($filter)]);
    }

    /**
     * Writes a table-scoped listing with the table's own qualifier.
     */
    public static function described(string $keyword, TableReference $table, PatternFilter|ConditionFilter|null $filter): Tree
    {
        return new Tree('show', [Build::keyword('SHOW ' . $keyword), Build::keyword('FROM'), Relations::target($table, Dialect::MySql), ...Filters::write($filter)]);
    }
}
