<?php

declare(strict_types=1);

namespace Fuzz\Target;

use RuntimeException;
use SqlSemantics\Binder;

/**
 * The total-analysis property for arbitrary SQL generated from the complete grammar.
 */
final class SemanticsTarget
{
    /**
     * Uses the same public analyzer consumers use.
     */
    public function __construct(public readonly Binder $binder)
    {
    }

    /**
     * Requires semantic structure, deterministic resolution, and strict-binding agreement.
     *
     * @throws RuntimeException
     */
    public function verify(string $sql, string $input): void
    {
        try {
            $analysis = $this->binder->analyze($sql);
            if ($sql === '' || $analysis->statement->source->toString() !== $sql) {
                throw new RuntimeException('Analysis lost the original statement.');
            }
            (new GraphProperties($this->binder->schema->dialect))->statement($analysis->statement);
            if (serialize($analysis) !== serialize($this->binder->analyze($sql))) {
                throw new RuntimeException('Semantic analysis is not deterministic.');
            }
            if ($analysis->diagnostics === [] && serialize($analysis->statement) !== serialize($this->binder->bind($sql))) {
                throw new RuntimeException('Diagnostic-free analysis disagrees with strict binding.');
            }
        } catch (RuntimeException $error) {
            throw new RuntimeException('Semantic property failed for ' . $this->binder->schema->grammarVersion . "\nInput hex: " . bin2hex($input) . "\nSQL:\n" . $sql . "\n" . $error->getMessage(), 0, $error);
        }
    }
}
