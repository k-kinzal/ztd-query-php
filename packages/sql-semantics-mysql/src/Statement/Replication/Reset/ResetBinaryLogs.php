<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Reset;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\Magnitudes;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `RESET BINARY LOGS AND GTIDS [TO n]`, written RESET MASTER in the legacy vocabulary.
 *
 * Mirrors the REFRESH_SOURCE flag of SQLCOM_RESET with LEX::next_binlog_file_nr.
 * The vocabulary is the one of the release (MYSQL-REPLICATION-RELEASE-001):
 * RESET MASTER up to 8.1, RESET BINARY LOGS AND GTIDS from 8.2, where 8.2 and
 * 8.3 read RESET MASTER as its synonym. TO needs MySQL 8.0 or later. Facts:
 * a TO number with a fraction (ER_ONLY_INTEGERS_ALLOWED), or 0 or greater
 * than 2000000000 (ER_RESET_SOURCE_TO_VALUE_OUT_OF_RANGE), is a RefusedSetting
 * diagnostic.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset-binary-logs-and-gtids.html,
 * https://dev.mysql.com/doc/refman/8.0/en/reset-master.html,
 * https://dev.mysql.com/doc/relnotes/mysql/8.2/en/news-8-2-0.html.
 *
 * @visibility public
 * @example Reading RESET MASTER as its synonym
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.3.0'))->analyze('reset master to 5')->toString() // => 'RESET BINARY LOGS AND GTIDS TO 5'
 */
final class ResetBinaryLogs implements ResetTarget
{
    use Snapshot;

    /**
     * The greatest file number TO accepts (MAX_ALLOWED_FN_EXT_RESET_BIN_LOGS).
     */
    private const LAST = 2000000000;

    /**
     * @param Terminology $terminology The vocabulary of the release
     * @param Numeral|null $first The number of the first new binary log file, when TO is written
     */
    public function __construct(public readonly Terminology $terminology, public readonly ?Numeral $first = null)
    {
    }

    /**
     * Checks the vocabulary and TO against the release and reports a refused file number.
     */
    public function deriveTarget(Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        $releases = new Releases();
        Check::input($releases->binaryLogs($release) === $this->terminology, 'RESET BINARY LOGS AND GTIDS is written in the vocabulary of the release.');
        Check::input($this->first === null || !$releases->legacy($release), 'RESET MASTER TO needs MySQL 8.0 or later.');
        if ($this->first === null) {
            return;
        }
        $magnitudes = new Magnitudes();
        if ($magnitudes->fractional($this->first)) {
            $derivation->report(new RefusedSetting(ReplicationError::FractionalNumber));
        } elseif ($magnitudes->integer($this->first) === 0 || $magnitudes->exceeds($this->first, self::LAST)) {
            $derivation->report(new RefusedSetting(ReplicationError::FileNumberOutOfRange));
        }
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        if ($this->terminology === Terminology::Legacy) {
            $out->keyword('MASTER');
        } else {
            $out->keyword('BINARY', 'LOGS', 'AND', 'GTIDS');
        }
        if ($this->first !== null) {
            $out->keyword('TO')->node($this->first);
        }
    }
}
