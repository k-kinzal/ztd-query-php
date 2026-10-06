<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Replica;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `STOP REPLICA [threads] [FOR CHANNEL 'name']`, or STOP SLAVE.
 *
 * Mirrors SQLCOM_REPLICA_STOP: the thread flags in written order (none means
 * both threads). The spelling SLAVE or REPLICA is kept
 * (MYSQL-REPLICATION-RELEASE-001). A channel needs MySQL 5.7 or later.
 * Rule: MYSQL-STOP-REPLICA-001. Facts: a line feed in the channel name is a
 * RefusedSetting diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stop-replica.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Stopping the applier thread of a channel
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze("stop slave sql_thread for channel 'c'")->toString() // => "STOP SLAVE SQL_THREAD FOR CHANNEL 'c'"
 */
final class StopReplica implements Statement
{
    use Snapshot;

    /**
     * @var list<ReplicaThread> The threads in written order; empty for both
     */
    public readonly array $threads;

    /**
     * @param Terminology $terminology The spelling: STOP SLAVE or STOP REPLICA
     * @param list<ReplicaThread> $threads The threads in written order; empty for both
     * @param Text|null $channel The replication channel, when FOR CHANNEL is written
     */
    public function __construct(public readonly Terminology $terminology, array $threads = [], public readonly ?Text $channel = null)
    {
        $this->threads = Check::listOf($threads, ReplicaThread::class, 'STOP REPLICA names a list of threads.');
    }

    /**
     * Checks the release and reports a refused channel name.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        Check::input((new Releases())->replica($release, $this->terminology), 'STOP ' . ($this->terminology === Terminology::Legacy ? 'SLAVE' : 'REPLICA') . ' is not a statement of this release.');
        Check::input($this->channel === null || $release !== GrammarRelease::MySql5651, 'A replication channel needs MySQL 5.7 or later.');
        (new SourceSettings())->lineFeed($derivation, $this->channel);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('STOP', $this->terminology === Terminology::Legacy ? 'SLAVE' : 'REPLICA');
        foreach ($this->threads as $position => $thread) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->keyword($thread->value);
        }
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
