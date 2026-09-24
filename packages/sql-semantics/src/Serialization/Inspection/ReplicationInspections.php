<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Inspection;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query\Inspection\ReplicationVocabulary;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal as SqlText;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection\Replication;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes binary log, relay log, and replica inspections; the vocabulary operand selects REPLICA or SLAVE spellings.
 * @visibility SqlSemantics
 */
final class ReplicationInspections
{
    /**
     * Returns null for statements outside the replication inspections.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Replication\ShowBinaryLogsStatement => Build::keyword('SHOW BINARY LOGS'),
            $statement instanceof Replication\ShowBinaryLogStatusStatement => Build::keyword($statement->binaryLogSpelling() ? 'SHOW BINARY LOG STATUS' : 'SHOW MASTER STATUS'),
            $statement instanceof Replication\ShowBinaryLogEventsStatement => new Tree('show', [Build::keyword('SHOW BINLOG EVENTS'), ...self::events($statement->log, $statement->position), ...SessionInspections::window($statement->limit)]),
            $statement instanceof Replication\ShowRelayLogEventsStatement => new Tree('show', [Build::keyword('SHOW RELAYLOG EVENTS'), ...self::events($statement->log, $statement->position), ...SessionInspections::window($statement->limit), ...self::channel($statement->channel)]),
            $statement instanceof Replication\ShowReplicaStatusStatement => new Tree('show', [Build::keyword($statement->vocabulary === ReplicationVocabulary::Current ? 'SHOW REPLICA STATUS' : 'SHOW SLAVE STATUS'), ...self::channel($statement->channel)]),
            $statement instanceof Replication\ShowReplicasStatement => Build::keyword($statement->vocabulary === ReplicationVocabulary::Current ? 'SHOW REPLICAS' : 'SHOW SLAVE HOSTS'),
            default => null,
        };
    }

    /**
     * @return list<Tree> IN with the log file name as a string and FROM with the position as written, each when given
     */
    public static function events(?string $log, ?Literal $position): array
    {
        return [
            ...($log === null ? [] : [Build::keyword('IN'), self::text($log)]),
            ...($position === null ? [] : [Build::keyword('FROM'), Expressions::write($position)]),
        ];
    }

    /**
     * @return list<Tree> FOR CHANNEL with the channel name as a string, or nothing
     */
    public static function channel(?string $channel): array
    {
        return $channel === null ? [] : [Build::keyword('FOR CHANNEL'), self::text($channel)];
    }

    /**
     * Writes a name as a MySQL string literal.
     */
    public static function text(string $name): Tree
    {
        return new Tree('literal', [new Atom('literal', SqlText::encode($name, Dialect::MySql)[0])]);
    }
}
