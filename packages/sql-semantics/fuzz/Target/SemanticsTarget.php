<?php

declare(strict_types=1);

namespace Fuzz\Target;

use RuntimeException;
use SqlSemantics\Binder;

/**
 * The total-binding property for arbitrary SQL generated from the complete grammar.
 */
final class SemanticsTarget
{
    /**
     * Uses the same public binder consumers use.
     */
    public function __construct(public readonly Binder $binder)
    {
    }

    /**
     * Requires semantic structure, deterministic resolution, and strict-binding agreement.
     *
     * @throws RuntimeException
     */
    public function verify(string $sql): void
    {
        try {
            $statement = $this->binder->bind($sql, strict: false);
        } catch (\SqlSemantics\InvalidSql $invalid) {
            $this->diagnostic($sql, $invalid);
            return;
        }
        if ($sql === '' || $statement->source->toString() !== $sql) {
            throw new RuntimeException('Binding lost the original statement.');
        }
        $script = $this->binder->bindAll($sql, strict: false);
        if (count($script) !== 1 || SemanticFacts::read($statement) !== SemanticFacts::read($script[0])) {
            throw new RuntimeException('Single-statement binding disagrees with script binding.');
        }
        $serialized = (new \SqlSemantics\SimpleSerializer())->serialize($statement);
        $roundTrip = $this->binder->bind($serialized, strict: false);
        if (SemanticFacts::read($statement) !== SemanticFacts::read($roundTrip) || $serialized !== $roundTrip->toString()) {
            throw new RuntimeException('Serialization changed semantic structure or is not idempotent. SQL: ' . $serialized);
        }
        if (SemanticFacts::read($statement) !== SemanticFacts::read($this->binder->bind($sql, strict: false))) {
            throw new RuntimeException('Semantic binding is not deterministic.');
        }
        if ($statement->diagnostics === [] && SemanticFacts::read($statement) !== SemanticFacts::read($this->binder->bind($sql))) {
            throw new RuntimeException('Binding without diagnostics disagrees with strict binding.');
        }
    }

    /**
     * A syntactically valid but impossible request must be diagnosed deterministically.
     * @throws RuntimeException
     */
    public function diagnostic(string $sql, \SqlSemantics\InvalidSql $expected): void
    {
        try {
            $this->binder->bind($sql, strict: false);
        } catch (\SqlSemantics\InvalidSql $actual) {
            if ($actual->violation === $expected->violation) {
                return;
            }
        }
        throw new RuntimeException('The invalid-input diagnosis is not deterministic.');
    }
}
