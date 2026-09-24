<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Server;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\GtidBoundary;
use SqlSemantics\Model\Configuration\Replication\RelayPosition;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;
use SqlSemantics\Model\Configuration\Replication\ReplicationCredential;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Configuration\Replication\SourcePosition;
use SqlSemantics\Model\Configuration\Replication\UntilCondition;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Server\Replication;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes replication commands in the vocabulary of the release they were bound against.
 * @visibility SqlSemantics
 */
final class ReplicationCommands
{
    /**
     * Writes FOR CHANNEL with the name as a string literal, or nothing for the default channel.
     * @return list<Tree>
     */
    public static function channel(?string $channel): array
    {
        return $channel === null ? [] : [Build::keyword('FOR CHANNEL'), new Tree('literal', [new Atom('literal', Literal::encode($channel, Dialect::MySql)[0])])];
    }

    /**
     * Writes START REPLICA or STOP REPLICA, spelled SLAVE before MySQL 8.0.
     */
    public static function replica(Replication\StartReplicaStatement|Replication\StopReplicaStatement $statement): Tree
    {
        $legacy = ReplicationRelease::legacy($statement->origin);
        $verb = Build::keyword(($statement instanceof Replication\StartReplicaStatement ? 'START ' : 'STOP ') . ($legacy ? 'SLAVE' : 'REPLICA'));
        $threads = $statement->threads === [] ? [] : [Build::separated(array_map(static fn (ReplicaThread $thread): Tree => Build::keyword($thread->value), $statement->threads))];
        if ($statement instanceof Replication\StopReplicaStatement) {
            return new Tree('stop-replica', [$verb, ...$threads, ...self::channel($statement->channel)]);
        }
        $until = $statement->until === null ? [] : [Build::keyword('UNTIL'), self::until($statement->until, $legacy)];
        return new Tree('start-replica', [$verb, ...$threads, ...$until, ...self::credentials($statement->credentials, false), ...self::channel($statement->channel)]);
    }

    /**
     * Writes START GROUP_REPLICATION with its credentials, or STOP GROUP_REPLICATION.
     */
    public static function group(Replication\StartGroupReplicationStatement|Replication\StopGroupReplicationStatement $statement): Tree
    {
        if ($statement instanceof Replication\StopGroupReplicationStatement) {
            return Build::keyword('STOP GROUP_REPLICATION');
        }
        return new Tree('start-group-replication', [Build::keyword('START GROUP_REPLICATION'), ...self::credentials($statement->credentials, true)]);
    }

    /**
     * Writes a stop point in the vocabulary of the release.
     */
    public static function until(UntilCondition $until, bool $legacy): Tree
    {
        $source = $legacy ? 'MASTER' : 'SOURCE';
        return match (true) {
            $until instanceof SourcePosition => Build::separated([self::assignment($source . '_LOG_FILE', $until->file), self::assignment($source . '_LOG_POS', $until->position)]),
            $until instanceof RelayPosition => Build::separated([self::assignment('RELAY_LOG_FILE', $until->file), self::assignment('RELAY_LOG_POS', $until->position)]),
            $until instanceof GtidBoundary => self::assignment($until->boundary->value, $until->gtids),
            default => Build::keyword('SQL_AFTER_MTS_GAPS'),
        };
    }

    /**
     * Writes credentials separated by commas for group replication and by spaces for replica threads.
     * @param list<ReplicationCredential> $credentials
     * @return list<Tree>
     */
    public static function credentials(array $credentials, bool $separated): array
    {
        $items = array_map(static fn (ReplicationCredential $credential): Tree => self::assignment($credential->option->value, $credential->value), $credentials);
        return $separated && $items !== [] ? [Build::separated($items)] : $items;
    }

    /**
     * Writes KEYWORD = value.
     */
    public static function assignment(string $keyword, Expression $value): Tree
    {
        return new Tree('replication-option', [Build::keyword($keyword), Build::keyword('='), Expressions::write($value)]);
    }
}
