<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;

/**
 * Derives the rows EXPLAIN returns, from its format and options.
 *
 * Rule: MYSQL-EXPLAIN-ROWS-001. Format names are compared without regard to
 * case. TRADITIONAL returns one row per table of the plan with the columns
 * of the layout of MYSQL-SHOW-ROWS-001 (in MySQL 5.6 EXTENDED adds
 * `filtered` and PARTITIONS adds `partitions`); JSON, and TREE from MySQL
 * 8.0 on, return one column `EXPLAIN`. Without FORMAT, MySQL 5.x uses
 * TRADITIONAL and MySQL 8.0 and later the format of the explain_format
 * system variable, so the shape depends on the session. EXPLAIN ANALYZE
 * returns one `EXPLAIN` column in every format it accepts and refuses
 * TRADITIONAL. EXPLAIN INTO (8.1 and later) stores the JSON plan in a user
 * variable, returns no rows and requires FORMAT=JSON. Another format name
 * is refused (ER_UNKNOWN_EXPLAIN_FORMAT). A refused statement returns no
 * rows. Terminates: a fixed table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html,
 * https://dev.mysql.com/doc/refman/8.4/en/explain-output.html,
 * https://dev.mysql.com/doc/refman/5.6/en/explain-extended.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ExplainFacts
{
    /**
     * Records the rows of an EXPLAIN, or reports why the server refuses it.
     */
    public function derive(Derivation $derivation, ?Name $format, bool $analyze, ?ExplainModifier $modifier, ?UserVariable $into): void
    {
        $named = $format === null ? null : strtoupper($format->value);
        if ($into !== null) {
            $derivation->scalar($into, $derivation->environment());
        }
        $refused = $this->refusal($derivation->context->profile->grammar, $named, $analyze, $into !== null);
        if ($refused !== null) {
            $derivation->report(new UtilityMisuse($refused));

            return;
        }
        if ($into === null) {
            $this->rows($derivation, $named, $analyze, $modifier);
        }
    }

    /**
     * Answers the rule a format and the options break, or null when the server accepts them.
     */
    public function refusal(GrammarRelease $release, ?string $format, bool $analyze, bool $into): ?UtilityRule
    {
        $known = $this->legacy($release) ? ['TRADITIONAL', 'JSON'] : ['TRADITIONAL', 'JSON', 'TREE'];

        return match (true) {
            $format !== null && !in_array($format, $known, true) => UtilityRule::UnknownExplainFormat,
            $analyze && $format === 'TRADITIONAL' => UtilityRule::AnalyzeFormat,
            $into && $format !== 'JSON' => UtilityRule::ExplainIntoFormat,
            default => null,
        };
    }

    /**
     * Records the rows of an accepted EXPLAIN that returns rows.
     */
    public function rows(Derivation $derivation, ?string $format, bool $analyze, ?ExplainModifier $modifier): void
    {
        $facts = new ShowFacts();
        if ($analyze || ($format !== null && $format !== 'TRADITIONAL')) {
            $facts->rows($derivation, Report::ExplainDocument);

            return;
        }
        if ($format === null && !$this->legacy($derivation->context->profile->grammar)) {
            $derivation->output($facts->query($facts->open(new SessionState('system variable @@explain_format'))->shape, $derivation->context->columnNames));

            return;
        }
        $facts->rows($derivation, match ($modifier) {
            null => Report::Explain,
            ExplainModifier::Extended => Report::ExplainExtended,
            ExplainModifier::Partitions => Report::ExplainPartitions,
        });
    }

    /**
     * Tells whether a release is MySQL 5.6 or 5.7, which have no TREE format and no explain_format variable.
     */
    public function legacy(GrammarRelease $release): bool
    {
        return $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
    }
}
