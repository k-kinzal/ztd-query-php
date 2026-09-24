<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Server;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\Source;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Server\Replication;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Session\Roles;

/**
 * Writes CHANGE REPLICATION SOURCE TO (CHANGE MASTER TO before MySQL 8.0) and CHANGE REPLICATION FILTER from their operands.
 * @visibility SqlSemantics
 */
final class ChangeCommands
{
    /**
     * Writes the source options in the vocabulary of the release the statement was bound against.
     */
    public static function source(Replication\ChangeReplicationSourceStatement $statement): Tree
    {
        $legacy = ReplicationRelease::legacy($statement->origin);
        $settings = array_map(static fn (Source\SourceSetting $setting): Tree => new Tree('replication-option', [
            Build::keyword($legacy ? $setting->option()->legacy() : $setting->option()->value),
            Build::keyword('='),
            self::setting($setting),
        ]), $statement->settings);
        return new Tree('change-replication-source', [Build::keyword($legacy ? 'CHANGE MASTER TO' : 'CHANGE REPLICATION SOURCE TO'), Build::separated($settings), ...ReplicationCommands::channel($statement->channel)]);
    }

    /**
     * Writes the value of one source option.
     */
    public static function setting(Source\SourceSetting $setting): Tree
    {
        return match (true) {
            $setting instanceof Source\SourceText, $setting instanceof Source\SourceNumber => Expressions::write($setting->value),
            $setting instanceof Source\SourceFlag => new Tree('literal', [new Atom('literal', $setting->enabled ? '1' : '0')]),
            $setting instanceof Source\IgnoredServers => Build::parentheses(Build::separated(array_map(Expressions::write(...), $setting->servers))),
            $setting instanceof Source\PrivilegeChecks => $setting->account === null ? Build::keyword('NULL') : Roles::accounts([$setting->account]),
            $setting instanceof Source\PrimaryKeyCheck, $setting instanceof Source\AnonymousGtids => Build::keyword($setting->value),
            $setting instanceof Source\AnonymousGtidUuid => Expressions::write($setting->uuid),
            default => Build::keyword('DEFAULT'),
        };
    }

    /**
     * Writes every filter rule with its complete value list.
     */
    public static function filter(Replication\ChangeReplicationFilterStatement $statement): Tree
    {
        $filters = array_map(static fn (Filter\ReplicationFilter $filter): Tree => new Tree('replication-filter', [
            Build::keyword($filter->rule()->value),
            Build::keyword('='),
            Build::parentheses(Build::separated(self::values($filter))),
        ]), $statement->filters);
        return new Tree('change-replication-filter', [Build::keyword('CHANGE REPLICATION FILTER'), Build::separated($filters), ...ReplicationCommands::channel($statement->channel)]);
    }

    /**
     * Writes the listed values of one filter rule.
     * @return list<Tree>
     */
    public static function values(Filter\ReplicationFilter $filter): array
    {
        return match (true) {
            $filter instanceof Filter\DatabaseFilter => array_map(static fn (string $database): Tree => Build::identifier([$database], Dialect::MySql), $filter->databases),
            $filter instanceof Filter\TableFilter => array_map(static fn ($table): Tree => Build::identifier($table->parts, Dialect::MySql), $filter->tables),
            $filter instanceof Filter\WildTableFilter => array_map(Expressions::write(...), $filter->patterns),
            $filter instanceof Filter\RewriteFilter => array_map(static fn (Filter\DatabaseRewrite $rewrite): Tree => Build::parentheses(Build::separated([Build::identifier([$rewrite->from], Dialect::MySql), Build::identifier([$rewrite->to], Dialect::MySql)])), $filter->rewrites),
            default => [],
        };
    }
}
