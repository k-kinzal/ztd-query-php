<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Replication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes publications with every object prefixed by its kind and options in a fixed order.
 * @visibility SqlSemantics
 */
final class Publications
{
    /**
     * Returns null for other statements.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof Statement\CreatePublicationStatement && !$statement instanceof Statement\CreateAllTablesPublicationStatement && !$statement instanceof Statement\CreateObjectsPublicationStatement && !$statement instanceof Statement\AlterPublicationOptionsStatement && !$statement instanceof Statement\AlterPublicationObjectsStatement) {
            return null;
        }
        $name = Build::identifier([$statement->name], Dialect::PostgreSql);
        return match (true) {
            $statement instanceof Statement\CreatePublicationStatement => new Tree('create-publication', [Build::keyword('CREATE PUBLICATION'), $name, ...self::options($statement->options, true)]),
            $statement instanceof Statement\CreateAllTablesPublicationStatement => new Tree('create-publication', [Build::keyword('CREATE PUBLICATION'), $name, Build::keyword('FOR ALL TABLES'), ...self::options($statement->options, true)]),
            $statement instanceof Statement\CreateObjectsPublicationStatement => new Tree('create-publication', [Build::keyword('CREATE PUBLICATION'), $name, Build::keyword('FOR'), self::objects($statement->objects), ...self::options($statement->options, true)]),
            $statement instanceof Statement\AlterPublicationOptionsStatement => new Tree('alter-publication', [Build::keyword('ALTER PUBLICATION'), $name, Build::keyword('SET'), ...self::options($statement->options, false)]),
            $statement instanceof Statement\AlterPublicationObjectsStatement => new Tree('alter-publication', [Build::keyword('ALTER PUBLICATION'), $name, Build::keyword($statement->change->value), self::objects($statement->objects)]),
        };
    }

    /**
     * Each object with its own TABLE or TABLES IN SCHEMA prefix.
     * @param non-empty-list<Operand\PublicationMember> $objects
     */
    public static function objects(array $objects): Tree
    {
        return Build::separated(array_map(static fn (Operand\PublicationMember $object): Tree => match (true) {
            $object instanceof Operand\PublishedTable => new Tree('published-table', [
                Build::keyword('TABLE'),
                Relations::target($object->table, Dialect::PostgreSql),
                ...($object->columns === [] ? [] : [Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], Dialect::PostgreSql), $object->columns)))]),
                ...($object->filter === null ? [] : [Build::keyword('WHERE'), Build::parentheses(Expressions::write($object->filter))]),
            ]),
            $object instanceof Operand\PublishedSchema => new Tree('published-schema', [Build::keyword('TABLES IN SCHEMA'), Build::identifier([$object->name], Dialect::PostgreSql)]),
            default => Build::keyword('TABLES IN SCHEMA CURRENT_SCHEMA'),
        }, $objects));
    }

    /**
     * The specified options in a parenthesized list, with WITH for a definition; nothing when none is specified.
     * @return list<Tree>
     */
    public static function options(Operand\PublicationOptions $options, bool $with): array
    {
        $items = [];
        if ($options->publish !== null) {
            $text = implode(', ', array_map(static fn (Operand\PublishedOperation $operation): string => $operation->value, $options->publish));
            $items[] = new Tree('option', [Build::keyword('publish ='), new Tree('literal', [new Atom('literal', Literal::encode($text, Dialect::PostgreSql)[0])])]);
        }
        if ($options->viaPartitionRoot !== null) {
            $items[] = Build::keyword('publish_via_partition_root = ' . ($options->viaPartitionRoot ? 'true' : 'false'));
        }
        return $items === [] ? [] : [...($with ? [Build::keyword('WITH')] : []), Build::parentheses(Build::separated($items))];
    }
}
