<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Closure;
use Error;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;

/**
 * Parses generated SQL and reports a statement the grammar should have accepted.
 *
 * Every statement sql-faker generates is derived from the same grammar the
 * parse table was built from, so a rejection is a finding: it surfaces as
 * an Error for PHP-Fuzzer to record together with the input.
 */
final class ParseTarget
{
    /**
     * @param Closure(string): Node $parse Parses one statement, throwing on rejection
     * @param string $grammarVersion Grammar version that produced the statement, e.g. "mysql-8.4.7"
     */
    public function __construct(
        private readonly Closure $parse,
        private readonly string $grammarVersion,
    ) {
    }

    /**
     * Verifies that the parser accepts the generated statement.
     *
     * @param string $sql Statement produced by the grammar
     * @param string $input Fuzzer input that produced the statement, so a finding can be replayed
     *
     * @throws Error When the statement is empty or the parser rejects it
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\nInput (hex): " . bin2hex($input));
        }
        try {
            ($this->parse)($sql);
        } catch (SourceException $rejection) {
            throw new Error(
                "The parser rejected generated SQL\n" .
                "Grammar: {$this->grammarVersion}\n" .
                'Input (hex): ' . bin2hex($input) . "\n" .
                "SQL: {$sql}\n" .
                "Error: {$rejection->getMessage()}",
                0,
                $rejection,
            );
        }
    }
}
