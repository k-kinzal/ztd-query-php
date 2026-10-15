<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StopReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicas;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus;
use SqlSemantics\Statement\Node;

/**
 * Derives replication vocabulary notices from retained semantic operands.
 *
 * These notices belong to the statement's meaning even when a later condition
 * prevents execution. MySQL 8.0 through 8.3 warn about each legacy occurrence;
 * consumers need neither original input nor keyword inspection.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/replication-statements.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Vocabulary
{
    /**
     * Records the release-specific notice before deriving later operands.
     */
    public function derive(Node $node, Derivation $derivation): void
    {
        if (!Deprecated::ReplicationSpelling->warnedIn($derivation->context->profile->grammar)) {
            return;
        }
        $names = match (true) {
            $node instanceof StartReplica && $node->terminology === Terminology::Legacy => ['START SLAVE', 'START REPLICA'],
            $node instanceof StopReplica && $node->terminology === Terminology::Legacy => ['STOP SLAVE', 'STOP REPLICA'],
            $node instanceof ShowReplicaStatus && $node->terminology === Terminology::Legacy => ['SHOW SLAVE STATUS', 'SHOW REPLICA STATUS'],
            $node instanceof ShowReplicas && $node->terminology === Terminology::Legacy => ['SHOW SLAVE HOSTS', 'SHOW REPLICAS'],
            $node instanceof ChangeReplicationSource && $node->synonym => ['CHANGE MASTER', 'CHANGE REPLICATION SOURCE'],
            $node instanceof ResetReplica && $node->synonym => ['RESET SLAVE', 'RESET REPLICA'],
            $node instanceof SourceOption && $node->synonym => [$node->kind->warningName(Terminology::Legacy), $node->kind->warningName(Terminology::Current)],
            default => null,
        };
        if ($names !== null) {
            $derivation->warn(new Deprecation(Deprecated::ReplicationSpelling, "'" . $names[0] . "' is deprecated and will be removed in a future release. Please use " . $names[1] . ' instead'), $node, false);
        }
    }
}
