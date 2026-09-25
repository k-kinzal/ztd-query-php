<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlObject;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Server\ServerOptions;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Server as Statement;
use SqlSemantics\Model\Statement\Definition\MySql\View\AlterViewStatement;
use SqlSemantics\Serialization\Definition\Constraints;
use SqlSemantics\Serialization\Definition\View\Views;
use SqlSemantics\Serialization\Query\Queries;

/**
 * Writes server definitions, loadable function registrations, and view alterations from their operands.
 * @visibility SqlSemantics
 */
final class ServerDefinitions
{
    /**
     * Returns null for statements outside these definitions.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateServerStatement => new Tree('create-server', [
                Build::keyword('CREATE SERVER'), StorageDefinitions::name($statement->name), Build::keyword('FOREIGN DATA WRAPPER'), StorageDefinitions::name($statement->wrapper), self::options($statement->options),
            ]),
            $statement instanceof Statement\AlterServerStatement => new Tree('alter-server', [Build::keyword('ALTER SERVER'), StorageDefinitions::name($statement->name), self::options($statement->options)]),
            $statement instanceof Statement\CreateLoadableFunctionStatement => new Tree('create-loadable-function', [
                Build::keyword('CREATE' . ($statement->aggregate ? ' AGGREGATE' : '') . ' FUNCTION' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), StorageDefinitions::name($statement->name),
                Build::keyword('RETURNS ' . $statement->returns->value . ' SONAME'), StorageDefinitions::text($statement->library),
            ]),
            $statement instanceof AlterViewStatement => self::view($statement),
            default => null,
        };
    }

    /**
     * Writes the named options in a fixed order.
     */
    public static function options(ServerOptions $options): Tree
    {
        $parts = [];
        foreach (['USER' => $options->user, 'HOST' => $options->host, 'DATABASE' => $options->database, 'OWNER' => $options->owner, 'PASSWORD' => $options->password, 'SOCKET' => $options->socket] as $keyword => $text) {
            if ($text !== null) {
                $parts[] = new Tree('server-option', [Build::keyword($keyword), StorageDefinitions::text($text)]);
            }
        }
        if ($options->port !== null) {
            $parts[] = Build::keyword('PORT ' . $options->port);
        }
        return new Tree('server-options', [Build::keyword('OPTIONS'), Build::parentheses(Build::separated($parts))]);
    }

    /**
     * Writes ALTER VIEW with its properties, declared names, query, and check option.
     */
    public static function view(AlterViewStatement $statement): Tree
    {
        return new Tree('alter-view', [
            Build::keyword('ALTER'), ...Views::mysql($statement->properties, Dialect::MySql, true), Build::keyword('VIEW'), Build::identifier($statement->name->parts, Dialect::MySql),
            ...($statement->columns === [] ? [] : [Constraints::columns($statement->columns, Dialect::MySql)]),
            Build::keyword('AS'), Queries::write($statement->query),
            ...($statement->check === ViewCheck::None ? [] : [Build::keyword('WITH ' . $statement->check->value . ' CHECK OPTION')]),
        ]);
    }
}
