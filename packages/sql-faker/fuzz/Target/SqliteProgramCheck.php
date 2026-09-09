<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Target;

use FFI;
use Override;
use SqlFaker\Coverage\Verification\VerificationResult;

/**
 * Checks every statement tail with SQLite 3.47.2 prepare_v3 without stepping generated SQL.
 * @see https://www.sqlite.org/c3ref/prepare.html
 */
final class SqliteProgramCheck implements SyntaxCheck
{
    private readonly SqliteNative $native;

    /**
     * Binds an explicitly supplied SQLite library, independently checking its version.
     * @throws InfrastructureFailure When the pinned library is unavailable
     */
    public function __construct(string $library)
    {
        $this->native = new SqliteNative($library);
        if ($this->native->text('sqlite3_libversion') !== '3.47.2') {
            throw new InfrastructureFailure('SQLite program oracle requires exactly 3.47.2.');
        }
    }

    /**
     * Reads compile options from the actual FFI library rather than PHP's possibly different linked SQLite.
     * @return array<string, string>
     */
    public function configuration(): array
    {
        $options = [];
        for ($index = 0; ($option = $this->native->text('sqlite3_compileoption_get', $index)) !== null; ++$index) {
            $options[] = $option;
        }
        sort($options);
        return ['version' => '3.47.2', 'checkMode' => 'prepare-all-tails-no-step', 'compileOptions' => implode(',', $options)];
    }

    /**
     * Semantic rejection leaves the rest of the program unverified and never counts as accepted syntax.
     * @throws InfrastructureFailure When a fresh parser session cannot be created
     * @throws SyntaxFailure When a statement has invalid syntax
     */
    #[Override]
    public function verify(string $sql, string $input): VerificationResult
    {
        if (str_contains($sql, "\0")) {
            throw new SyntaxFailure('SQLite program contains an embedded NUL.');
        }
        $db = $this->native->allocate('sqlite3 *');
        if ($this->native->integer('sqlite3_open', ':memory:', FFI::addr($db)) !== 0) {
            throw new InfrastructureFailure('Cannot open SQLite program parser.');
        }
        try {
            $remaining = $sql;
            while ($remaining !== '') {
                $statement = $this->native->allocate('sqlite3_stmt *');
                $tail = $this->native->allocate('const char *');
                $buffer = $this->native->allocate('char[' . (strlen($remaining) + 1) . ']');
                FFI::memcpy($buffer, $remaining, strlen($remaining));
                $code = $this->native->integer('sqlite3_prepare_v3', $db, $buffer, strlen($remaining), 0, FFI::addr($statement), FFI::addr($tail));
                if (!FFI::isNull($statement)) {
                    $this->native->integer('sqlite3_finalize', $statement);
                }
                if ($code !== 0) {
                    $result = (new SqliteSyntaxCheck())->verify($remaining, $input);
                    if ($result->status === 'accepted') {
                        throw new InfrastructureFailure('SQLite FFI and PDO disagree on the same statement.');
                    }
                    return new VerificationResult($result->status, $result->code, $result->message, 'sqlite3_prepare_v3:tail-unverified');
                }
                $next = FFI::string($tail);
                if (strlen($next) >= strlen($remaining)) {
                    throw new InfrastructureFailure('SQLite parser did not consume its input.');
                }
                $remaining = $next;
            }
            return new VerificationResult('accepted');
        } finally {
            $this->native->integer('sqlite3_close', $db);
        }
    }
}
