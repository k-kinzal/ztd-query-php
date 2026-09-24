<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Loading\Copy;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;
use SqlSemantics\Serialization\Statements;

/**
 * Writes PostgreSQL COPY with a parenthesized list of the options that differ from the server defaults.
 * @visibility SqlSemantics
 */
final class CopyCommands
{
    /**
     * Returns null for statements outside COPY.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Copy\CopyFromStatement => new Tree('copy', [
                ...self::table($statement->table, $statement->columns),
                Build::keyword('FROM'),
                self::endpoint($statement->input, 'STDIN'),
                ...self::options($statement->options),
                ...($statement->where === null ? [] : [Build::keyword('WHERE'), Expressions::write($statement->where)]),
            ]),
            $statement instanceof Copy\CopyToStatement => new Tree('copy', [...self::table($statement->table, $statement->columns), Build::keyword('TO'), self::endpoint($statement->destination, 'STDOUT'), ...self::options($statement->options)]),
            $statement instanceof Copy\CopyQueryStatement => new Tree('copy', [Build::keyword('COPY'), Build::parentheses(Statements::write($statement->query)), Build::keyword('TO'), self::endpoint($statement->destination, 'STDOUT'), ...self::options($statement->options)]),
            default => null,
        };
    }

    /**
     * @param list<string> $columns
     * @return list<Tree>
     */
    public static function table(\SqlSemantics\Model\Relation\TableReference $table, array $columns): array
    {
        return [Build::keyword('COPY'), Relations::target($table, Dialect::PostgreSql), ...($columns === [] ? [] : [self::names($columns)])];
    }

    /**
     * Writes a file, a PROGRAM or the client keyword for the direction.
     */
    public static function endpoint(Copy\Endpoint\CopyEndpoint $endpoint, string $client): Tree
    {
        return match (true) {
            $endpoint instanceof Copy\Endpoint\CopyFile => Expressions::write($endpoint->path),
            $endpoint instanceof Copy\Endpoint\CopyProgram => new Tree('program', [Build::keyword('PROGRAM'), Expressions::write($endpoint->command)]),
            default => Build::keyword($client),
        };
    }

    /**
     * @return list<Tree> WITH and the parenthesized options, or nothing when every option has its default
     * @throws InvalidStructure
     */
    public static function options(Copy\CopyOptions $options): array
    {
        $items = [
            ...($options->format === Copy\CopyFormat::Text ? [] : [self::option('FORMAT', DatabaseCommands::value($options->format->value))]),
            ...($options->freeze ? [Build::keyword('FREEZE')] : []),
            ...self::text('DELIMITER', $options->delimiter),
            ...self::text('NULL', $options->null),
            ...self::text('DEFAULT', $options->default),
            ...($options->header === Copy\CopyHeader::Absent ? [] : [self::option('HEADER', Build::keyword(strtoupper($options->header->value)))]),
            ...self::text('QUOTE', $options->quote),
            ...self::text('ESCAPE', $options->escape),
            ...self::choice('FORCE_QUOTE', $options->forceQuote),
            ...self::choice('FORCE_NOT_NULL', $options->forceNotNull),
            ...self::choice('FORCE_NULL', $options->forceNull),
            ...self::text('ENCODING', $options->encoding),
            ...($options->onError === null ? [] : [self::option('ON_ERROR', DatabaseCommands::value($options->onError->value))]),
            ...($options->logVerbosity === Copy\CopyLogVerbosity::Default ? [] : [self::option('LOG_VERBOSITY', DatabaseCommands::value($options->logVerbosity->value))]),
        ];
        return $items === [] ? [] : [Build::keyword('WITH'), Build::parentheses(Build::separated($items))];
    }

    /**
     * @return list<Tree>
     * @throws InvalidStructure
     */
    public static function text(string $name, ?string $value): array
    {
        return $value === null ? [] : [self::option($name, DatabaseCommands::value($value))];
    }

    /**
     * @return list<Tree>
     */
    public static function choice(string $name, ?Copy\ColumnChoice $choice): array
    {
        return match (true) {
            $choice instanceof Copy\ListedColumns => [self::option($name, self::names($choice->columns))],
            $choice === null => [],
            default => [self::option($name, Build::keyword('*'))],
        };
    }

    /**
     * Writes one option name followed by its argument.
     */
    public static function option(string $name, Tree $argument): Tree
    {
        return new Tree('copy-option', [Build::keyword($name), $argument]);
    }

    /**
     * @param list<string> $columns
     */
    public static function names(array $columns): Tree
    {
        return Build::parentheses(Build::separated(array_map(static fn (string $column): Tree => Build::identifier([$column], Dialect::PostgreSql), $columns)));
    }
}
