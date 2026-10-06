<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Derives the options of CHANGE REPLICATION SOURCE and the log positions of UNTIL, and the checks the server applies to their values.
 *
 * Rule: MYSQL-REPLICATION-SOURCE-001. An option needs a release that has it
 * (MYSQL-REPLICATION-RELEASE-001). The heartbeat period is a number literal
 * and receives its scalar fact. The server's parser refuses: a line feed in
 * a value read by TEXT_STRING_sys_nonewline (every string option except
 * SOURCE_COMPRESSION_ALGORITHMS, ER_WRONG_VALUE); a password of more than 32
 * bytes (5.7 and later); a delay greater than 2147483647 (MASTER_DELAY_MAX);
 * a heartbeat period greater than 4294967 seconds (REPLICA_MAX_HEARTBEAT_PERIOD,
 * compared as the server does after converting to a floating number);
 * REQUIRE_ROW_FORMAT, SOURCE_CONNECTION_AUTO_FAILOVER and GTID_ONLY other
 * than 0 or 1, and a fraction in the last two; an anonymous GTID UUID whose
 * first 36 characters are not a UUID in its text form. Each is a RefusedSetting
 * diagnostic. Terminates: one pass over the options.
 * Source: sql/sql_yacc.yy (source_def, master_def, TEXT_STRING_sys_nonewline,
 * assign_gtids_to_anonymous_transactions_def),
 * https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SourceSettings
{
    /**
     * The longest password the server accepts, in bytes.
     */
    private const PASSWORD = 32;

    /**
     * The longest delay the server accepts, in seconds.
     */
    private const DELAY = 2147483647;

    /**
     * The longest heartbeat period the server accepts, in seconds.
     */
    private const HEARTBEAT = 4294967;

    /**
     * Derives every option.
     *
     * @param list<SourceOption> $options
     */
    public function options(Derivation $derivation, array $options): void
    {
        foreach ($options as $option) {
            $this->option($derivation, $option);
        }
    }

    /**
     * Derives one option and reports a refused value.
     */
    public function option(Derivation $derivation, SourceOption $option): void
    {
        $release = $derivation->context->profile->grammar;
        Check::input((new Releases())->option($release, $option->kind), $option->kind->value . ' is not an option of this release.');
        if ($option->value instanceof NumberLiteral) {
            $derivation->scalar($option->value, $derivation->environment());
        }
        $error = $this->error($option, $release);
        if ($error !== null) {
            $derivation->report(new RefusedSetting($error));
        }
    }

    /**
     * Answers the check an option value fails, if any.
     */
    public function error(SourceOption $option, GrammarRelease $release): ?ReplicationError
    {
        $value = $option->value;
        if ($value instanceof Text) {
            return $this->text($option->kind, $value, $release);
        }
        if ($value instanceof Numeral) {
            return $this->number($option->kind, $value);
        }
        if ($value instanceof NumberLiteral) {
            return (float) $value->text > self::HEARTBEAT ? ReplicationError::HeartbeatOutOfRange : null;
        }

        return null;
    }

    /**
     * Answers the check a string value fails, if any.
     */
    public function text(SourceOptionKind $kind, Text $value, GrammarRelease $release): ?ReplicationError
    {
        if ($kind === SourceOptionKind::AssignGtidsToAnonymousTransactions) {
            return preg_match('/\A[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}/', $value->value) === 1 ? null : ReplicationError::InvalidUuid;
        }
        if ($kind !== SourceOptionKind::CompressionAlgorithms && str_contains($value->value, "\n")) {
            return ReplicationError::LineFeed;
        }
        if ($kind === SourceOptionKind::Password && $release !== GrammarRelease::MySql5651 && strlen($value->value) > self::PASSWORD) {
            return ReplicationError::PasswordTooLong;
        }

        return null;
    }

    /**
     * Answers the check a number value fails, if any.
     */
    public function number(SourceOptionKind $kind, Numeral $value): ?ReplicationError
    {
        $magnitudes = new Magnitudes();
        if ($kind === SourceOptionKind::ConnectionAutoFailover || $kind === SourceOptionKind::GtidOnly) {
            return $magnitudes->fractional($value) ? ReplicationError::FractionalNumber : ($magnitudes->flag($value) ? null : ReplicationError::SwitchValue);
        }
        if ($kind === SourceOptionKind::RequireRowFormat) {
            return $magnitudes->flag($value) ? null : ReplicationError::RowFormatValue;
        }
        if ($kind === SourceOptionKind::Delay) {
            return $magnitudes->exceeds($value, self::DELAY) ? ReplicationError::DelayOutOfRange : null;
        }

        return null;
    }

    /**
     * Reports a line feed in a string the server reads with TEXT_STRING_sys_nonewline.
     */
    public function lineFeed(Derivation $derivation, ?Text $value): void
    {
        if ($value !== null && str_contains($value->value, "\n")) {
            $derivation->report(new RefusedSetting(ReplicationError::LineFeed));
        }
    }
}
