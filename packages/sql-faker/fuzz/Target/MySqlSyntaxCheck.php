<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use PDO;
use PDOException;

/**
 * Prepares generated SQL against MySQL and reports unexpected rejections.
 *
 * SQL PREPARE reaches the server directly, avoiding PDO's emulation fallback.
 * Unsupported preparation is reported as incomplete verification. Explicit
 * schema-dependent rejections are counted separately from parser acceptance;
 * syntax errors and unclassified rejections remain findings.
 */
final class MySqlSyntaxCheck
{
    /**
     * @param PDO $pdo Connection to the MySQL instance under test
     * @param string $grammarVersion Grammar version that produced the statement
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $grammarVersion,
    ) {
    }

    /**
     * Verifies that MySQL parses the generated statement.
     *
     * @param string $sql Statement produced by the grammar
     * @param string $input Original fuzzer input, encoded as hex by the target
     *
     * @throws InfrastructureFailure When the database environment is unavailable
     * @throws SyntaxFailure When MySQL rejects the statement for a reason the grammar should not produce
     */
    public function verify(string $sql, string $input): VerificationResult
    {
        if ($sql === '') {
            throw new SyntaxFailure('Statement generation returned an empty string.');
        }

        try {
            $quoted = $this->pdo->quote($sql);
            if ($quoted === false) {
                throw new InfrastructureFailure('Cannot quote generated SQL for server PREPARE.');
            }
            $this->pdo->exec('SET @sql_faker_input = ' . $quoted);
            $this->pdo->exec('PREPARE sql_faker_check FROM @sql_faker_input');
            $this->pdo->exec('DEALLOCATE PREPARE sql_faker_check');
            return VerificationResult::Accepted;
        } catch (PDOException $rejection) {

            $errorCode = $rejection->errorInfo[1] ?? 0;
            if (in_array($errorCode, [2002, 2006, 2013, 1040], true)) {
                throw new InfrastructureFailure('MySQL verification connection failed.', 0, $rejection);
            }
            if ($errorCode === 1295) {
                return VerificationResult::Incomplete;
            }

            $acceptable = match ($errorCode) {

                1054 => true,

                1046 => true,

                1527 => true,

                1273 => true,

                1327 => true,

                3708 => true,

                1407 => true,

                1049 => true,

                1319 => true,

                1305 => true,

                1096 => true,

                1791 => true,

                1286 => true,

                1235 => true,

                1690 => true,

                3652 => true,

                3709 => true,

                1525 => true,

                3942 => false,

                1051 => true,

                3980 => true,

                1193 => true,

                1277 => true,

                1641 => true,
                default => false,
            };

            if ($acceptable) {
                return VerificationResult::Rejected;
            }

            throw new SyntaxFailure(
                "Unexpected error in generated SQL\n" .
                "Grammar: {$this->grammarVersion}\n" .
                "Input (hex): {$input}\n" .
                "SQL: $sql\n" .
                'SQLSTATE: ' . (is_scalar($rejection->errorInfo[0] ?? null) ? (string) $rejection->errorInfo[0] : 'unknown') . "\n" .
                'Error Code: ' . (is_scalar($rejection->errorInfo[1] ?? null) ? (string) $rejection->errorInfo[1] : 'unknown') . "\n" .
                "Error: {$rejection->getMessage()}",
                0,
                $rejection
            );
        }
    }
}
