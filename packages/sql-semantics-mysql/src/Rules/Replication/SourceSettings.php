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
 * first 36 characters are not a UUID in its text form. UUID values are
 * retained with the complete option and reported during derivation. Numeric
 * switches retain their numeral and source position. The same conditions are
 * available to incomplete input checks. Terminates: one pass over the options.
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
        (new Vocabulary())->derive($option, $derivation);
        $release = $derivation->context->profile->grammar;
        Check::input((new Releases())->option($release, $option->kind), $option->kind->value . ' is not an option of this release.');
        if ($option->value instanceof NumberLiteral) {
            $derivation->scalar($option->value, $derivation->environment());
        }
        $condition = $this->condition($option, $release);
        $warning = $this->warning($option, $release, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context)->replicaNetTimeout);
        if ($warning !== null) {
            $derivation->warn($warning, $option);
        }
        if ($condition !== null) {
            $derivation->report($condition->problem);
            $derivation->warn($condition, $option);
        }
    }

    /**
     * Retains a source option's condition and its parsing phase without discarding the request.
     */
    public function condition(SourceOption $option, GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure
    {
        $error = $this->error($option, $release);
        if ($error === null) {
            return null;
        }
        if ($option->value instanceof Numeral && in_array($option->kind, [SourceOptionKind::RequireRowFormat, SourceOptionKind::ConnectionAutoFailover, SourceOptionKind::GtidOnly], true)) {
            $problem = new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber($option->kind, $option->value, $option->value->inputSource->offset ?? 0);
            return new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, $option->kind !== SourceOptionKind::RequireRowFormat);
        }
        $problem = new RefusedSetting($error, $error === ReplicationError::DelayOutOfRange && $option->value instanceof Numeral ? $option->value : null, in_array($error, [ReplicationError::LineFeed, ReplicationError::InvalidUuid], true) && $option->value instanceof Text ? $option->value : null);
        return new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, $error !== ReplicationError::DelayOutOfRange);
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
            return $this->heartbeat($value);
        }

        return null;
    }

    /**
     * Refuses a heartbeat literal outside the protocol's supported interval.
     */
    public function heartbeat(NumberLiteral $value): ?ReplicationError
    {
        return (float) $value->text > self::HEARTBEAT ? ReplicationError::HeartbeatOutOfRange : null;
    }

    /**
     * Resolves the notice for one written heartbeat period before later input is read.
     */
    public function warning(SourceOption $option, GrammarRelease $release, ?int $timeout = null): ?\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod
    {
        if ($option->kind !== SourceOptionKind::HeartbeatPeriod || !$option->value instanceof NumberLiteral || $this->heartbeat($option->value) !== null) {
            return null;
        }
        $period = (float) $option->value->text;
        if ($period > 0 && $period < 0.001) {
            return new \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod(\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition::BelowMinimum);
        }
        $timeout ??= $release === GrammarRelease::MySql5651 ? 3600 : 60;
        if ($period <= $timeout) {
            return null;
        }

        return new \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod(in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition::AboveSlaveTimeout : \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition::AboveReplicaTimeout);
    }

    /**
     * Answers the check a string value fails, if any.
     */
    public function text(SourceOptionKind $kind, Text $value, GrammarRelease $release): ?ReplicationError
    {
        if ($kind === SourceOptionKind::AssignGtidsToAnonymousTransactions) {
            return preg_match('/\A[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}/', $value->value) === 1 ? null : ReplicationError::InvalidUuid;
        }
        if ($kind !== SourceOptionKind::CompressionAlgorithms && !(new \SqlSemantics\Platform\MySql\Rules\Literal\LineText())->valid($value)) {
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
        $conversion = $value === null ? null : (new ChannelName())->conversion($value, $derivation->context->profile->grammar);
        if ($conversion !== null) {
            $derivation->warn($conversion, $value);
        }
        $problem = (new ChannelName())->problem($value);
        if ($problem !== null) {
            $derivation->report($problem);
        }
    }
}
