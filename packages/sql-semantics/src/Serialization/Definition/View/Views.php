<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\View;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\PostgreSqlViewProperties;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View as Statement;
use SqlSemantics\Serialization\Definition\Constraints;
use SqlSemantics\Serialization\Definition\SchemaCommands;
use SqlSemantics\Serialization\Definition\Storage;
use SqlSemantics\Serialization\Query\Queries;

/**
 * Writes ordinary and materialized view declarations with their typed options.
 * @visibility SqlSemantics
 */
final class Views
{
    /**
     * Returns null for statements outside the view family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof CreateViewStatement => self::view($statement),
            $statement instanceof Statement\CreateMaterializedViewStatement => self::materialized($statement),
            $statement instanceof Statement\RefreshMaterializedViewStatement => new Tree('refresh-materialized-view', [Build::keyword('REFRESH MATERIALIZED VIEW' . ($statement->concurrently ? ' CONCURRENTLY' : '')), Build::identifier($statement->name->parts, Dialect::PostgreSql), ...($statement->withData ? [] : [Build::keyword('WITH NO DATA')])]),
            $statement instanceof Statement\DropMaterializedViewsStatement => SchemaCommands::drop('MATERIALIZED VIEW', $statement->names, $statement->ifExists, $statement->behavior, Dialect::PostgreSql),
            default => null,
        };
    }

    /**
     * Writes the view's declaration options, query, and declared output names.
     */
    public static function view(CreateViewStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        $properties = $statement->properties;
        $head = [Build::keyword('CREATE' . ($statement->replace ? ' OR REPLACE' : '') . ($statement->temporary ? ' TEMPORARY' : ''))];
        if ($properties instanceof MySqlViewProperties) {
            array_push($head, ...self::mysql($properties, $dialect));
        }
        $head[] = Build::keyword(($properties instanceof PostgreSqlViewProperties && $properties->recursive ? 'RECURSIVE ' : '') . 'VIEW' . ($statement->ifNotExists ? ' IF NOT EXISTS' : ''));
        $head[] = Build::identifier($statement->name->parts, $dialect);
        if ($statement->columns !== []) {
            $head[] = Constraints::columns($statement->columns, $dialect);
        }
        if ($properties instanceof PostgreSqlViewProperties && $properties->parameters !== []) {
            array_push($head, Build::keyword('WITH'), Build::parentheses(Storage::parameters($properties->parameters, $dialect)));
        }
        return new Tree('create-view', [...$head, Build::keyword('AS'), Queries::write($statement->query), ...($statement->check === ViewCheck::None ? [] : [Build::keyword('WITH ' . $statement->check->value . ' CHECK OPTION')])]);
    }

    /**
     * Writes the MySQL view properties; CREATE VIEW leaves out the default SQL SECURITY DEFINER, while ALTER VIEW
     * writes every stated security because leaving it out keeps the view's current one.
     *
     * @return list<Tree>
     */
    public static function mysql(MySqlViewProperties $properties, Dialect $dialect, bool $alteration = false): array
    {
        $parts = [];
        if ($properties->algorithm !== ViewAlgorithm::Undefined) {
            $parts[] = Build::keyword('ALGORITHM = ' . $properties->algorithm->value);
        }
        if ($properties->definer !== null) {
            $parts[] = Build::keyword('DEFINER =');
            $parts[] = \SqlSemantics\Serialization\Definition\MySqlRemovals::accounts([$properties->definer]);
        }
        if ($properties->security !== null && ($alteration || $properties->security !== ViewSecurity::Definer)) {
            $parts[] = Build::keyword('SQL SECURITY ' . $properties->security->value);
        }
        return $parts;
    }

    /**
     * Writes the stored view's storage placement, query, and population request.
     */
    public static function materialized(Statement\CreateMaterializedViewStatement $statement): Tree
    {
        $dialect = Dialect::PostgreSql;
        return new Tree('create-materialized-view', [
            Build::keyword('CREATE' . ($statement->unlogged ? ' UNLOGGED' : '') . ' MATERIALIZED VIEW' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')),
            Build::identifier($statement->name->parts, $dialect),
            ...($statement->columns === [] ? [] : [Constraints::columns($statement->columns, $dialect)]),
            ...($statement->accessMethod === null ? [] : [Build::keyword('USING'), Build::identifier([$statement->accessMethod], $dialect)]),
            ...($statement->storageParameters === [] ? [] : [Build::keyword('WITH'), Build::parentheses(Storage::parameters($statement->storageParameters, $dialect))]),
            ...($statement->tablespace === null ? [] : [Build::keyword('TABLESPACE'), Build::identifier([$statement->tablespace], $dialect)]),
            Build::keyword('AS'),
            Queries::write($statement->query),
            ...($statement->withData ? [] : [Build::keyword('WITH NO DATA')]),
        ]);
    }
}
