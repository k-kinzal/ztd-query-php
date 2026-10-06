<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Replica;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Rules\Replication\ReplicaConditions;
use SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `START REPLICA [threads] [UNTIL …] [USER = …] [PASSWORD = …] [DEFAULT_AUTH = …] [PLUGIN_DIR = …] [FOR CHANNEL …]`, or START SLAVE.
 *
 * Mirrors SQLCOM_REPLICA_START: the thread flags in written order (none
 * means both threads), the UNTIL condition in LEX_SOURCE_INFO, and the
 * connection options of LEX::replica_connection in their fixed grammar order.
 * The spelling SLAVE or REPLICA is kept (MYSQL-REPLICATION-RELEASE-001); the
 * log positions of UNTIL are in the vocabulary of the release. A channel
 * needs MySQL 5.7 or later. Rule: MYSQL-START-REPLICA-001. Facts: the checks
 * of MYSQL-REPLICA-UNTIL-001 and of the log positions
 * (MYSQL-REPLICATION-SOURCE-001), and a line feed in the channel name, as
 * RefusedSetting diagnostics.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-replica.html,
 * https://dev.mysql.com/doc/refman/5.7/en/start-slave.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Starting the receiver thread with credentials
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("start replica relay_thread user = 'u' password = 'p'")->toString() // => "START REPLICA IO_THREAD USER = 'u' PASSWORD = 'p'"
 */
final class StartReplica implements Statement
{
    use Snapshot;

    /**
     * @var list<ReplicaThread> The threads in written order; empty for both
     */
    public readonly array $threads;

    /**
     * @param Terminology $terminology The spelling: START SLAVE or START REPLICA
     * @param list<ReplicaThread> $threads The threads in written order; empty for both
     * @param ReplicaUntil|null $until The UNTIL clause, when written
     * @param Text|null $user The USER option, when written
     * @param Text|null $password The PASSWORD option, when written
     * @param Text|null $defaultAuth The DEFAULT_AUTH option, when written
     * @param Text|null $pluginDir The PLUGIN_DIR option, when written
     * @param Text|null $channel The replication channel, when FOR CHANNEL is written
     */
    public function __construct(
        public readonly Terminology $terminology,
        array $threads = [],
        public readonly ?ReplicaUntil $until = null,
        public readonly ?Text $user = null,
        public readonly ?Text $password = null,
        public readonly ?Text $defaultAuth = null,
        public readonly ?Text $pluginDir = null,
        public readonly ?Text $channel = null,
    ) {
        $this->threads = Check::listOf($threads, ReplicaThread::class, 'START REPLICA names a list of threads.');
    }

    /**
     * Checks the release, derives the UNTIL positions and reports refused conditions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        $releases = new Releases();
        Check::input($releases->replica($release, $this->terminology), 'START ' . ($this->terminology === Terminology::Legacy ? 'SLAVE' : 'REPLICA') . ' is not a statement of this release.');
        Check::input($this->channel === null || $release !== GrammarRelease::MySql5651, 'A replication channel needs MySQL 5.7 or later.');
        $conditions = new ReplicaConditions();
        if ($this->until !== null) {
            foreach ($this->until->positions as $position) {
                Check::input($position->terminology === $releases->source($release), 'A log position is written in the vocabulary of the release.');
            }
            (new SourceSettings())->options($derivation, $this->until->positions);
            $error = $conditions->until($this->until);
            if ($error !== null) {
                $derivation->report(new RefusedSetting($error));
            }
        }
        $credentials = $this->user !== null || $this->password !== null || $this->defaultAuth !== null || $this->pluginDir !== null;
        if ($conditions->credentialsRefused($this->threads, $credentials)) {
            $derivation->report(new RefusedSetting(ReplicationError::ApplierWithCredentials));
        }
        (new SourceSettings())->lineFeed($derivation, $this->channel);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('START', $this->terminology === Terminology::Legacy ? 'SLAVE' : 'REPLICA');
        foreach ($this->threads as $position => $thread) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->keyword($thread->value);
        }
        $out->node($this->until);
        foreach (['USER' => $this->user, 'PASSWORD' => $this->password, 'DEFAULT_AUTH' => $this->defaultAuth, 'PLUGIN_DIR' => $this->pluginDir] as $keyword => $value) {
            if ($value !== null) {
                $out->keyword($keyword)->symbol('=')->node($value);
            }
        }
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
