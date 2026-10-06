<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Reset;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `RESET REPLICA [ALL] [FOR CHANNEL 'name']`, written RESET SLAVE in the legacy vocabulary.
 *
 * Mirrors the REFRESH_REPLICA flag of SQLCOM_RESET with LEX::reset_replica_info.
 * The vocabulary is the one of the release (MYSQL-REPLICATION-RELEASE-001):
 * RESET SLAVE of 8.0 to 8.3 is read as its synonym RESET REPLICA. A channel
 * needs MySQL 5.7 or later. Facts: a line feed in the channel name is a
 * RefusedSetting diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset-replica.html,
 * https://dev.mysql.com/doc/refman/8.0/en/reset-slave.html.
 *
 * @visibility public
 * @example Reading RESET SLAVE as its synonym
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.0.44'))->analyze('reset slave all')->toString() // => 'RESET REPLICA ALL'
 */
final class ResetReplica implements ResetTarget
{
    use Snapshot;

    /**
     * @param Terminology $terminology The vocabulary of the release
     * @param bool $all Whether ALL is written: the connection settings are removed too
     * @param Text|null $channel The replication channel, when FOR CHANNEL is written
     */
    public function __construct(public readonly Terminology $terminology, public readonly bool $all = false, public readonly ?Text $channel = null)
    {
    }

    /**
     * Checks the vocabulary and the channel against the release.
     */
    public function deriveTarget(Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        Check::input((new Releases())->source($release) === $this->terminology, 'RESET REPLICA is written in the vocabulary of the release.');
        Check::input($this->channel === null || $release !== GrammarRelease::MySql5651, 'A replication channel needs MySQL 5.7 or later.');
        (new SourceSettings())->lineFeed($derivation, $this->channel);
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->terminology === Terminology::Legacy ? 'SLAVE' : 'REPLICA');
        if ($this->all) {
            $out->keyword('ALL');
        }
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
