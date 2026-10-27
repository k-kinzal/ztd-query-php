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

/**
 * `RESET BINARY LOGS AND GTIDS [TO n]`, written RESET MASTER in the legacy vocabulary.
 *
 * The vocabulary is the one of the release (MYSQL-REPLICATION-RELEASE-001):
 * RESET MASTER up to 8.1, RESET BINARY LOGS AND GTIDS from 8.2, where 8.2 and
 * 8.3 read RESET MASTER as its synonym. TO needs MySQL 8.0 or later. Facts:
 * a TO number with a fraction (ER_ONLY_INTEGERS_ALLOWED), or 0 or greater
 * than 2000000000 (ER_RESET_SOURCE_TO_VALUE_OUT_OF_RANGE), is refused at the
 * input boundary. A constructed target always has an integral index in range.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset-binary-logs-and-gtids.html,
 * https://dev.mysql.com/doc/refman/8.0/en/reset-master.html,
 * https://dev.mysql.com/doc/relnotes/mysql/8.2/en/news-8-2-0.html.
 *
 * @visibility public
 * @example Reading RESET MASTER as its synonym
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.3.0'))->analyze('reset master to 5')->toString() // => 'RESET MASTER TO 5'
 */
final class ResetBinaryLogs implements ResetTarget
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Terminology $terminology The vocabulary of the release
     * @param Numeral|null $first The number of the first new binary log file, when TO is written
     * @param bool $synonym Whether MASTER is written where BINARY LOGS AND GTIDS is the current vocabulary
     */
    public function __construct(public readonly Terminology $terminology, public readonly ?Numeral $first = null, public readonly bool $synonym = false, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(!$synonym || $terminology === Terminology::Current, 'The MASTER synonym applies to current binary-log terminology.');
        Check::input(self::problem($first) === null, 'A binary log index is an integer between 1 and 2000000000.');
    }

    /**
     * Checks the vocabulary and availability of TO against the release.
     */
    public function deriveTarget(Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        if ($this->synonym) {
            $notice = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ResetMaster;
            Check::input($notice->warnedIn($release), 'The MASTER synonym is accepted in MySQL 8.2 and 8.3.');
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation($notice), $this, false);
        }
        $releases = new Releases();
        Check::input($releases->binaryLogs($release) === $this->terminology, 'RESET BINARY LOGS AND GTIDS is written in the vocabulary of the release.');
        Check::input($this->first === null || !$releases->legacy($release), 'RESET MASTER TO needs MySQL 8.0 or later.');
    }

    /**
     * Describes an invalid file index independently of the later RESET items.
     */
    public static function problem(?Numeral $number): ?RefusedSetting
    {
        if ($number === null) {
            return null;
        }
        $magnitudes = new Magnitudes();
        if ($magnitudes->fractional($number)) {
            return new RefusedSetting(ReplicationError::FractionalNumber);
        }

        return $magnitudes->integer($number) === 0 || $magnitudes->exceeds($number, 2000000000) ? new RefusedSetting(ReplicationError::FileNumberOutOfRange, $number) : null;
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        if ($this->terminology === Terminology::Legacy || $this->synonym) {
            $out->keyword('MASTER');
        } else {
            $out->keyword('BINARY', 'LOGS', 'AND', 'GTIDS');
        }
        if ($this->first !== null) {
            $out->keyword('TO')->node($this->first);
        }
    }
}
