<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Facade\Semantics;
use Throwable;

/**
 * Every generated statement must be represented and written using its semantic data.
 */
final class RoundTripTarget
{
    public function __construct(
        private readonly Semantics $semantics,
        private readonly Formatter $compact,
        private readonly string $grammarVersion,
    ) {
    }

    /**
     * Records rejections, serialization failures and differences as fuzz findings.
     */
    public function verify(string $sql, string $input): void
    {
        $context = "Grammar: {$this->grammarVersion}\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}";
        if ($sql === '') {
            throw new Error("Statement generation returned an empty string\n{$context}");
        }
        $printed = null;
        try {
            $statement = $this->semantics->analyze($sql);
            $printed = $statement->toString();
            $expected = $this->compact->format($sql);
            $actual = $this->compact->format($printed);
        } catch (Throwable $failure) {
            throw new Error("Semantic round trip failed\n{$context}\nPrinted: {$printed}\nError: {$failure->getMessage()}", 0, $failure);
        }
        if ($actual !== $expected) {
            throw new Error("Semantic round trip changed the statement\n{$context}\nPrinted: {$printed}\nExpected: {$expected}\nActual: {$actual}");
        }
    }
}
