<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlSemantics\Core\Verification\Losslessness;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Traversal;
use Throwable;

/**
 * Every generated statement must be represented and written using its semantic data, losing nothing.
 *
 * Analysis itself requires the written SQL to be read back as the same
 * statement. The written SQL must also have the syntax of the generated SQL:
 * the same rules, tokens, spellings, and comments, apart from whitespace and
 * the letter case of keywords. sql-faker writes no comments, so the same
 * statement is also analyzed with comments written into some of the spaces
 * between its tokens, and every comment must come back before its token.
 */
final class RoundTripTarget
{
    private readonly Losslessness $losslessness;

    public function __construct(
        private readonly Semantics $semantics,
        private readonly string $grammarVersion,
    ) {
        $this->losslessness = new Losslessness($semantics->language());
    }

    /**
     * Records rejections, serialization failures and differences as fuzz findings.
     */
    public function verify(string $sql, string $input): void
    {
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\nGrammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input));
        }
        $this->roundTrip($sql, $input);
        $commented = $this->commented($sql, crc32($input));
        if ($commented !== $sql) {
            $this->roundTrip($commented, $input);
        }
    }

    /**
     * Requires the statement of the SQL to write it back with nothing lost.
     */
    private function roundTrip(string $sql, string $input): void
    {
        $context = "Grammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        $printed = null;
        try {
            $statement = $this->semantics->analyze($sql);
            $printed = $statement->toString();
            $difference = $this->losslessness->difference($sql, $printed);
        } catch (Throwable $failure) {
            throw new Error("Semantic round trip failed\n{$context}\nPrinted: {$printed}\nError: {$failure->getMessage()}", 0, $failure);
        }
        if ($difference !== null) {
            throw new Error("Semantic round trip lost information\n{$context}\nPrinted: {$printed}\nDifference: {$difference}");
        }
        if (Traversal::rewrite($statement->command, static fn (Element $value): Element => $value) !== $statement->command) {
            throw new Error("Rewriting without replacing anything rebuilt the statement\n{$context}");
        }
    }

    /**
     * Writes a block comment into up to three of the spaces between tokens, and a line comment at the end.
     */
    private function commented(string $sql, int $seed): string
    {
        $spaces = [];
        foreach ($this->semantics->language()->parser()->tokenize($sql) as $token) {
            if ($token->text !== '' && $token->leading !== '' && trim($token->leading) === '') {
                $spaces[] = $token->offset;
            }
        }
        if ($spaces === []) {
            return $sql;
        }
        $chosen = [];
        for ($comment = 0; $comment < 3; $comment++) {
            $chosen[$spaces[($seed >> ($comment * 8)) % count($spaces)]] = true;
        }
        krsort($chosen);
        foreach (array_keys($chosen) as $index => $offset) {
            $sql = substr($sql, 0, $offset) . "/* c{$index} */ " . substr($sql, $offset);
        }

        return $sql . "\n-- end";
    }
}
