<?php

declare(strict_types=1);

namespace Fuzz\Policy;

use Fuzz\Probe\ProbeResult;

/**
 * Decides which MySQL probe failures indicate grammar bugs and which ones are
 * expected because the fuzz target has no schema, data, or server features to
 * satisfy every generated statement.
 */
final class MySqlFuzzPolicy implements FuzzPolicy
{
    /** @var array<int, string> */
    private const STATE_ERROR_MESSAGES = [
        1046 => 'ER_NO_DB_ERROR: No database selected',
        1049 => "ER_BAD_DB_ERROR: Unknown database '%-.192s'",
        1051 => "ER_BAD_TABLE_ERROR: Unknown table '%-.129s'",
        1054 => "ER_BAD_FIELD_ERROR: Unknown column '%-.192s' in '%-.192s'",
        1096 => 'ER_NO_TABLES_USED: No tables used',
        1205 => 'ER_LOCK_WAIT_TIMEOUT: Lock wait timeout exceeded; try restarting transaction',
        1305 => 'ER_SP_DOES_NOT_EXIST: %s %s does not exist',
        1319 => 'ER_SP_COND_MISMATCH: Undefined CONDITION: %s',
        1327 => 'ER_SP_UNDECLARED_VAR: Undeclared variable: %s',
        3572 => 'ER_LOCK_NOWAIT: Statement aborted because lock(s) could not be acquired immediately and NOWAIT is set.',
    ];

    /** @var array<int, string> */
    private const ENVIRONMENT_ERROR_MESSAGES = [
        1235 => "ER_NOT_SUPPORTED_YET: This version of MySQL doesn't yet support '%s'",
        1286 => "ER_UNKNOWN_STORAGE_ENGINE: Unknown storage engine '%s'",
        3652 => 'ER_INVALID_VCPU_ID: Invalid cpu id %u',
    ];

    /** @var array<int, string> */
    private const SYNTAX_ERROR_MESSAGES = [
        1064 => "ER_PARSE_ERROR: %s near '%-.80s' at line %d",
    ];

    /**
     * Returns the SQL dialect handled by this policy.
     */
    public function dialect(): string
    {
        return 'mysql';
    }

    /**
     * Treats parser failures and unknown diagnostics as crashes, while allowing
     * state- and environment-dependent MySQL errors to be ignored.
     */
    public function classify(ProbeResult $probeResult): FuzzDecision
    {
        if ($probeResult->accepted) {
            return FuzzDecision::ignore('accepted', 'statement prepared successfully');
        }

        if ($probeResult->message === 'PDO::prepare returned false') {
            return FuzzDecision::crash('unknown', 'PDO::prepare returned false');
        }

        $errorCode = $probeResult->errorCode;
        if ($errorCode === null) {
            return FuzzDecision::crash('unknown', 'MySQL did not provide an error code');
        }

        if (isset(self::STATE_ERROR_MESSAGES[$errorCode])) {
            return FuzzDecision::ignore('state', self::STATE_ERROR_MESSAGES[$errorCode]);
        }

        if (isset(self::ENVIRONMENT_ERROR_MESSAGES[$errorCode])) {
            return FuzzDecision::ignore('environment', self::ENVIRONMENT_ERROR_MESSAGES[$errorCode]);
        }

        if (isset(self::SYNTAX_ERROR_MESSAGES[$errorCode])) {
            return FuzzDecision::crash('syntax', self::SYNTAX_ERROR_MESSAGES[$errorCode]);
        }

        return FuzzDecision::crash('contract', sprintf('Unhandled MySQL error code %d', $errorCode));
    }
}
