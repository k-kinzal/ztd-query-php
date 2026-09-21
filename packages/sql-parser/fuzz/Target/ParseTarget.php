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
 * an Error for PHP-Fuzzer to record together with the input. So is a tree
 * that does not write back the statement it was parsed from, byte for byte,
 * because the tree is meant to hold every byte of the text, comments and
 * whitespace included.
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
     * Verifies that the parser accepts the generated statement and writes it back unchanged.
     *
     * @param string $sql Statement produced by the grammar
     * @param string $input Fuzzer input that produced the statement, so a finding can be replayed
     *
     * @throws Error When the statement is empty, the parser rejects it, or the tree writes back other text
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\nInput (hex): " . bin2hex($input));
        }
        try {
            $tree = ($this->parse)($sql);
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
        $printed = $tree->toString();
        if ($printed !== $sql) {
            throw new Error(
                "The tree wrote back other text than the generated SQL\n" .
                "Grammar: {$this->grammarVersion}\n" .
                'Input (hex): ' . bin2hex($input) . "\n" .
                'Diverges at byte: ' . strspn($sql ^ $printed, "\0") . "\n" .
                "SQL: {$sql}\n" .
                "Tree: {$printed}",
            );
        }
    }
}
