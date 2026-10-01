<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Core\Analysis\ModelGraph;
use SqlSemantics\Facade\Semantics;
use Throwable;

/**
 * Every generated statement must lower into immutable semantic data and preserve its meaning on reconstruction.
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
            if ($this->compact->format($sql) !== $this->compact->format($printed)) {
                throw new Error('Reconstruction changed formatter-normalized input SQL.');
            }
            $expected = (new ModelGraph())->fingerprint($statement);
            $actual = (new ModelGraph())->fingerprint($this->semantics->analyze($printed));
        } catch (Throwable $failure) {
            throw new Error("Semantic round trip failed\n{$context}\nPrinted: {$printed}\nError: {$failure->getMessage()}", 0, $failure);
        }
        if ($actual !== $expected) {
            throw new Error("Semantic reconstruction changed the model\n{$context}\nPrinted: {$printed}\nExpected: {$expected}\nActual: {$actual}");
        }
    }
}
