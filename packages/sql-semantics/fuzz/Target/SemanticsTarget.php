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
    private readonly SchemaProperties $schemaProperties;

    /**
     * Uses the same public binder consumers use.
     */
    public function __construct(public readonly Binder $binder)
    {
        $this->schemaProperties = new SchemaProperties($binder->schema->dialect, $binder->schema->grammarVersion);
    }

    /**
     * Requires semantic structure, deterministic resolution, and strict-binding agreement.
     *
     * @throws RuntimeException
     */
    public function verify(string $sql, string $input): void
    {
        try {
            $statement = $this->binder->bind($sql, strict: false);
            $this->schemaProperties->verify($input);
            if ($sql === '' || $statement->source->toString() !== $sql) {
                throw new RuntimeException('Binding lost the original statement.');
            }
            $serialized = (new \SqlSemantics\SimpleSerializer())->serialize($statement);
            $roundTrip = $this->binder->bind($serialized, strict: false);
            if (SemanticFacts::read($statement) !== SemanticFacts::read($roundTrip) || $serialized !== $roundTrip->toString()) {
                throw new RuntimeException('Serialization changed semantic structure or is not idempotent. SQL: ' . $serialized);
            }
            (new GraphProperties($this->binder->schema->dialect))->statement($statement);
            if (SemanticFacts::read($statement) !== SemanticFacts::read($this->binder->bind($sql, strict: false))) {
                throw new RuntimeException('Semantic binding is not deterministic.');
            }
            if ($statement->diagnostics === [] && SemanticFacts::read($statement) !== SemanticFacts::read($this->binder->bind($sql))) {
                throw new RuntimeException('Binding without diagnostics disagrees with strict binding.');
            }
        } catch (RuntimeException $error) {
            throw new RuntimeException('Semantic property failed for ' . $this->binder->schema->grammarVersion . "\nInput hex: " . bin2hex($input) . "\nSQL:\n" . $sql . "\n" . $error->getMessage(), 0, $error);
        }
    }
}
